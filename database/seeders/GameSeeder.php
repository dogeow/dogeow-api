<?php

namespace Database\Seeders;

use App\Models\Game\GameItemDefinition;
use App\Models\Game\GameMapDefinition;
use App\Models\Game\GameMonsterDefinition;
use App\Models\Game\GameSkillDefinition;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GameSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->seedItemDefinitions();
        $this->seedSkillDefinitions();
        $this->seedMonsterDefinitions();
        $this->seedMapDefinitions();
    }

    private function seedItemDefinitions(): void
    {
        DB::table('game_item_definitions')->truncate();

        $items = require __DIR__.'/GameSeederData/items.php';

        foreach ($items as $item) {
            GameItemDefinition::create(array_merge($item, [
                'icon' => 'item_'.$item['id'].'.png',
                'is_active' => true,
            ]));
        }
    }

    private function seedSkillDefinitions(): void
    {
        $skillsDir = __DIR__.'/GameSeederData/skills';
        $skillFiles = [
            'skills_warrior.php',
            'skills_mage.php',
            'skills_ranger.php',
        ];
        $skills = [];
        foreach ($skillFiles as $file) {
            $path = $skillsDir.'/'.$file;
            if (file_exists($path)) {
                $skills = array_merge($skills, require $path);
            }
        }

        // 使用 updateOrCreate 根据名称更新或创建技能
        foreach ($skills as $skill) {
            $isActive = $skill['type'] === 'active';
            $baseDamage = $isActive ? ($skill['mana_cost'] * 2) : 0;
            $damagePerLevel = $isActive ? 5 : 0;
            $manaCostPerLevel = $isActive ? 2 : 0;

            \App\Models\Game\GameSkillDefinition::updateOrCreate(
                ['name' => $skill['name']],
                array_merge($skill, [
                    'type' => $skill['type'],
                    'class_restriction' => $skill['class_restriction'],
                    'mana_cost' => $skill['mana_cost'],
                    'cooldown' => $skill['cooldown'],
                    'description' => $skill['description'],
                    'effects' => $skill['effects'] ?? null,
                    'target_type' => $skill['target_type'] ?? 'single',
                    'icon' => 'skill_'.strtolower(str_replace(' ', '_', $skill['name'])).'.png',
                    'is_active' => true,
                    'max_level' => 10,
                    'base_damage' => $baseDamage,
                    'damage_per_level' => $damagePerLevel,
                    'mana_cost_per_level' => $manaCostPerLevel,
                ])
            );
        }
    }

    private function seedMonsterDefinitions(): void
    {
        $monsters = require __DIR__.'/GameSeederData/monsters.php';

        foreach ($monsters as $monster) {
            GameMonsterDefinition::create(array_merge($monster, [
                'icon' => 'monster_'.strtolower(str_replace(' ', '_', $monster['name'])).'.png',
                'is_active' => true,
            ]));
        }
    }

    private function seedMapDefinitions(): void
    {
        $maps = require __DIR__.'/GameSeederData/maps.php';

        // 按 ID 顺序取当前库中怪物，用于把配置里的“序号”转成真实 ID（避免多次 seed 后 ID 错位）
        $monsterIdsByOrder = GameMonsterDefinition::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->pluck('id')
            ->values()
            ->all();

        foreach ($maps as $index => $map) {
            $rawIds = $map['monster_ids'] ?? [];
            $resolvedIds = array_values(array_filter(array_map(
                fn ($ord) => $monsterIdsByOrder[$ord - 1] ?? null,
                array_map('intval', (array) $rawIds)
            )));
            if (empty($resolvedIds)) {
                $resolvedIds = array_slice($monsterIdsByOrder, 0, 2);
            }

            GameMapDefinition::updateOrCreate(
                [
                    'name' => $map['name'],
                    'act' => $map['act'],
                ],
                array_merge($map, [
                    'monster_ids' => $resolvedIds,
                    'background' => 'map_'.($index + 1).'.jpg',
                    'is_active' => true,
                ])
            );
        }

        // 为所有缺少怪物的地图补全 monster_ids（含历史/重复行）
        $mapList = $maps;
        GameMapDefinition::query()->chunk(50, function ($definitions) use ($mapList, $monsterIdsByOrder) {
            foreach ($definitions as $def) {
                $ids = $def->monster_ids;
                if (is_array($ids) && ! empty(array_filter($ids))) {
                    continue;
                }
                $match = collect($mapList)->first(
                    fn ($m) => $m['name'] === $def->name && (int) $m['act'] === (int) $def->act
                );
                if ($match && ! empty($monsterIdsByOrder)) {
                    $rawIds = $match['monster_ids'] ?? [];
                    $resolvedIds = array_values(array_filter(array_map(
                        fn ($ord) => $monsterIdsByOrder[$ord - 1] ?? null,
                        array_map('intval', (array) $rawIds)
                    )));
                    if (empty($resolvedIds)) {
                        $resolvedIds = array_slice($monsterIdsByOrder, 0, 2);
                    }
                    $def->update(['monster_ids' => $resolvedIds]);
                } elseif (! empty($monsterIdsByOrder)) {
                    $def->update(['monster_ids' => array_slice($monsterIdsByOrder, 0, 2)]);
                }
            }
        });
    }
}
