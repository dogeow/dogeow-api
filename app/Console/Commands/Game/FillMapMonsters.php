<?php

namespace App\Console\Commands\Game;

use App\Models\Game\GameMapDefinition;
use App\Models\Game\GameMonsterDefinition;
use Illuminate\Console\Command;

class FillMapMonsters extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'game:fill-map-monsters
                            {--dry-run : 仅列出将填充的地图，不写入数据库}
                            {--force : 无需确认直接执行}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '查找没有怪物的地图，并按等级范围自动填充合适的怪物';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        $emptyMaps = $this->findMapsWithoutMonsters();

        if ($emptyMaps->isEmpty()) {
            $this->info('所有地图都已有怪物配置，无需填充。');
            return self::SUCCESS;
        }

        $this->warn("发现 {$emptyMaps->count()} 个地图没有有效怪物：");
        $this->table(
            ['ID', '名称', '幕', '等级范围', '当前 monster_ids'],
            $emptyMaps->map(fn (GameMapDefinition $m) => [
                $m->id,
                $m->name,
                $m->act,
                "Lv.{$m->min_level}-{$m->max_level}",
                json_encode($m->monster_ids ?? [], JSON_UNESCAPED_UNICODE),
            ])->toArray()
        );

        $allMonsters = GameMonsterDefinition::query()
            ->where('is_active', true)
            ->orderBy('level')
            ->get();

        if ($allMonsters->isEmpty()) {
            $this->error('数据库中没有启用的怪物定义，无法填充。');
            return self::FAILURE;
        }

        $plans = [];
        foreach ($emptyMaps as $map) {
            $suitableIds = $this->pickMonstersForMap($map, $allMonsters);
            if (empty($suitableIds)) {
                $this->warn("  地图 [{$map->name}] 等级范围 Lv.{$map->min_level}-{$map->max_level} 内无可用怪物，已跳过。");
                continue;
            }
            $plans[] = [
                'map' => $map,
                'monster_ids' => $suitableIds,
                'monsters' => $allMonsters->whereIn('id', $suitableIds)->values(),
            ];
        }

        if (empty($plans)) {
            $this->info('没有可填充的地图（等级范围内无怪物）。');
            return self::SUCCESS;
        }

        $this->info('');
        $this->info('计划填充：');
        foreach ($plans as $plan) {
            $names = $plan['monsters']->map(fn ($m) => "{$m->name}(Lv.{$m->level})")->join(', ');
            $this->line("  [{$plan['map']->name}] => [{$names}]");
        }

        if ($dryRun) {
            $this->info('');
            $this->info('[dry-run] 未写入数据库。');
            return self::SUCCESS;
        }

        if (! $force && ! $this->confirm('是否执行填充？', true)) {
            $this->info('已取消。');
            return self::SUCCESS;
        }

        $updated = 0;
        foreach ($plans as $plan) {
            $plan['map']->update(['monster_ids' => array_values($plan['monster_ids'])]);
            $updated++;
        }

        $this->info("已为 {$updated} 个地图填充怪物。");
        return self::SUCCESS;
    }

    /**
     * 找出没有有效怪物的地图（monster_ids 为空或全部无效）
     */
    private function findMapsWithoutMonsters()
    {
        $maps = GameMapDefinition::query()->where('is_active', true)->get();
        $validMonsterIds = GameMonsterDefinition::query()
            ->where('is_active', true)
            ->pluck('id')
            ->flip()
            ->all();

        return $maps->filter(function (GameMapDefinition $map) use ($validMonsterIds) {
            $ids = $map->monster_ids;
            if ($ids === null || ! is_array($ids)) {
                return true;
            }
            $ids = array_map('intval', array_values($ids));
            $ids = array_filter($ids, fn ($id) => $id > 0);
            if (empty($ids)) {
                return true;
            }
            $hasValid = false;
            foreach ($ids as $id) {
                if (isset($validMonsterIds[$id])) {
                    $hasValid = true;
                    break;
                }
            }
            return ! $hasValid;
        })->values();
    }

    /**
     * 根据地图等级范围选取 2～4 个合适怪物（优先 normal，其次 elite，最后 boss）
     */
    private function pickMonstersForMap(GameMapDefinition $map, $allMonsters): array
    {
        $minLevel = max(1, $map->min_level - 2);
        $maxLevel = $map->max_level + 2;

        $candidates = $allMonsters->filter(function ($m) use ($minLevel, $maxLevel) {
            return $m->level >= $minLevel && $m->level <= $maxLevel;
        });

        if ($candidates->isEmpty()) {
            return [];
        }

        $byType = [
            'normal' => $candidates->where('type', 'normal')->values()->all(),
            'elite' => $candidates->where('type', 'elite')->values()->all(),
            'boss' => $candidates->where('type', 'boss')->values()->all(),
        ];

        $pick = [];
        $order = ['normal', 'elite', 'boss'];
        $targetCount = min(4, max(2, (int) ceil(($map->max_level - $map->min_level) / 10 + 2)));

        foreach ($order as $type) {
            $pool = $byType[$type];
            if (empty($pool) || count($pick) >= $targetCount) {
                continue;
            }
            $need = $targetCount - count($pick);
            $take = $type === 'boss' ? 1 : min($need, $type === 'normal' ? 2 : 2);
            $selected = $this->pickFromPool($pool, $take);
            foreach ($selected as $m) {
                $pick[$m->id] = $m->id;
            }
        }

        return array_values($pick);
    }

    /**
     * 从池子中选取 n 个（尽量覆盖等级分布）
     *
     * @param  array<int, GameMonsterDefinition>  $pool
     * @return array<GameMonsterDefinition>
     */
    private function pickFromPool(array $pool, int $n): array
    {
        if (count($pool) <= $n) {
            return array_values($pool);
        }
        $levels = array_map(fn ($m) => $m->level, $pool);
        $minL = min($levels);
        $maxL = max($levels);
        if ($maxL <= $minL) {
            return array_slice(array_values($pool), 0, $n);
        }
        $step = ($maxL - $minL) / max(1, $n - 1);
        $result = [];
        $pickedIds = [];
        for ($i = 0; $i < $n; $i++) {
            $targetLevel = $minL + $step * $i;
            $closest = null;
            $closestDist = PHP_INT_MAX;
            foreach ($pool as $m) {
                if (isset($pickedIds[$m->id])) {
                    continue;
                }
                $d = abs($m->level - $targetLevel);
                if ($d < $closestDist) {
                    $closestDist = $d;
                    $closest = $m;
                }
            }
            if ($closest !== null) {
                $result[] = $closest;
                $pickedIds[$closest->id] = true;
            }
        }
        return $result;
    }
}
