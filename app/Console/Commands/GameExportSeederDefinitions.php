<?php

namespace App\Console\Commands;

use App\Models\Game\GameItemDefinition;
use App\Models\Game\GameMonsterDefinition;
use App\Models\Game\GameSkillDefinition;
use Illuminate\Console\Command;

class GameExportSeederDefinitions extends Command
{
    protected $signature = 'game:export-seeder-definitions';

    protected $description = '从数据库导出 game_item_definitions、game_monster_definitions、game_skill_definitions，写入 database/seeders/GameSeederData/ 目录，供 GameSeeder 使用';

    public function handle(): int
    {
        $this->info('从数据库导出物品、怪物与技能定义...');

        $dir = database_path('seeders/GameSeederData');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $items = GameItemDefinition::query()->orderBy('id')->get();
        $monsters = GameMonsterDefinition::query()->orderBy('id')->get();
        $skills = GameSkillDefinition::query()->orderBy('id')->get();

        $itemsArray = $items->map(function (GameItemDefinition $row) {
            $arr = [
                'name' => $row->name,
                'type' => $row->type,
                'sub_type' => $row->sub_type,
                'base_stats' => $row->base_stats ?? [],
                'required_level' => (int) $row->required_level,
                'required_strength' => (int) ($row->required_strength ?? 0),
                'required_dexterity' => (int) ($row->required_dexterity ?? 0),
                'required_energy' => (int) ($row->required_energy ?? 0),
            ];
            if ($row->description !== null && $row->description !== '') {
                $arr['description'] = $row->description;
            }
            if (isset($row->sockets)) {
                $arr['sockets'] = (int) $row->sockets;
            }
            if ($row->gem_stats !== null && $row->gem_stats !== []) {
                $arr['gem_stats'] = $row->gem_stats;
            }
            return $arr;
        })->all();

        $monstersArray = $monsters->map(function (GameMonsterDefinition $row) {
            return [
                'name' => $row->name,
                'type' => $row->type,
                'level' => (int) $row->level,
                'hp_base' => (int) $row->hp_base,
                'hp_per_level' => (int) $row->hp_per_level,
                'attack_base' => (int) $row->attack_base,
                'attack_per_level' => (int) $row->attack_per_level,
                'defense_base' => (int) $row->defense_base,
                'defense_per_level' => (int) $row->defense_per_level,
                'experience_base' => (int) $row->experience_base,
                'experience_per_level' => (int) $row->experience_per_level,
                'drop_table' => $row->drop_table ?? [],
            ];
        })->all();

        $skillsArray = $skills->map(function (GameSkillDefinition $row) {
            $arr = [
                'name' => $row->name,
                'type' => $row->type,
                'class_restriction' => $row->class_restriction,
                'mana_cost' => (int) $row->mana_cost,
                'cooldown' => (int) $row->cooldown,
                'effects' => $row->effects ?? [],
            ];
            if ($row->description !== null && $row->description !== '') {
                $arr['description'] = $row->description;
            }
            if (isset($row->damage)) {
                $arr['damage'] = (int) $row->damage;
            }
            return $arr;
        })->all();

        $header = "<?php\n\n// 由 php artisan game:export-seeder-definitions 从数据库导出，供 GameSeeder 使用\nreturn ";
        file_put_contents($dir.'/items.php', $header.$this->varExportShort($itemsArray).";\n");
        file_put_contents($dir.'/monsters.php', $header.$this->varExportShort($monstersArray).";\n");
        file_put_contents($dir.'/skills.php', $header.$this->varExportShort($skillsArray).";\n");

        $this->info('已写入: '.$dir.'/items.php, monsters.php, skills.php');
        $this->info('物品: '.count($itemsArray).' 条，怪物: '.count($monstersArray).' 条，技能: '.count($skillsArray).' 条');
        $this->info('下次执行 php artisan db:seed --class=GameSeeder 将使用上述数据。');

        return self::SUCCESS;
    }

    private function varExportShort(mixed $var, int $indent = 0): string
    {
        $pad = str_repeat('    ', $indent);
        if (is_array($var)) {
            if (empty($var)) {
                return '[]';
            }
            $lines = ["[\n"];
            foreach ($var as $k => $v) {
                $key = is_int($k) ? '' : var_export($k, true).' => ';
                $lines[] = $pad.'    '.$key.$this->varExportShort($v, $indent + 1).",\n";
            }
            $lines[] = $pad.']';
            return implode('', $lines);
        }
        return var_export($var, true);
    }
}
