<?php

namespace Database\Seeders;

use App\Models\Game\GameItemDefinition;
use App\Models\Game\GameMapDefinition;
use App\Models\Game\GameMonsterDefinition;
use App\Models\Game\GameSkillDefinition;
use Illuminate\Database\Seeder;

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
        $items = [
            // 药水 - HP药水
            ['name' => '小型生命药水', 'type' => 'potion', 'sub_type' => 'hp', 'base_stats' => ['max_hp' => 30], 'required_level' => 1, 'description' => '恢复30点生命值'],
            ['name' => '中型生命药水', 'type' => 'potion', 'sub_type' => 'hp', 'base_stats' => ['max_hp' => 60], 'required_level' => 5, 'description' => '恢复60点生命值'],
            ['name' => '大型生命药水', 'type' => 'potion', 'sub_type' => 'hp', 'base_stats' => ['max_hp' => 100], 'required_level' => 10, 'description' => '恢复100点生命值'],
            ['name' => '超级生命药水', 'type' => 'potion', 'sub_type' => 'hp', 'base_stats' => ['max_hp' => 200], 'required_level' => 15, 'description' => '恢复200点生命值'],
            ['name' => '巨型生命药水', 'type' => 'potion', 'sub_type' => 'hp', 'base_stats' => ['max_hp' => 350], 'required_level' => 20, 'description' => '恢复350点生命值'],
            ['name' => '终极生命药水', 'type' => 'potion', 'sub_type' => 'hp', 'base_stats' => ['max_hp' => 500], 'required_level' => 25, 'description' => '恢复500点生命值'],
            ['name' => '神话生命药水', 'type' => 'potion', 'sub_type' => 'hp', 'base_stats' => ['max_hp' => 800], 'required_level' => 35, 'description' => '恢复800点生命值'],
            ['name' => '神圣生命药水', 'type' => 'potion', 'sub_type' => 'hp', 'base_stats' => ['max_hp' => 1200], 'required_level' => 45, 'description' => '恢复1200点生命值'],
            ['name' => '天使生命药水', 'type' => 'potion', 'sub_type' => 'hp', 'base_stats' => ['max_hp' => 1800], 'required_level' => 55, 'description' => '恢复1800点生命值'],
            ['name' => '神之生命药水', 'type' => 'potion', 'sub_type' => 'hp', 'base_stats' => ['max_hp' => 2500], 'required_level' => 65, 'description' => '恢复2500点生命值'],
            ['name' => '创世生命药水', 'type' => 'potion', 'sub_type' => 'hp', 'base_stats' => ['max_hp' => 3500], 'required_level' => 75, 'description' => '恢复3500点生命值'],
            ['name' => '永恒生命药水', 'type' => 'potion', 'sub_type' => 'hp', 'base_stats' => ['max_hp' => 5000], 'required_level' => 85, 'description' => '恢复5000点生命值'],
            ['name' => '混沌生命药水', 'type' => 'potion', 'sub_type' => 'hp', 'base_stats' => ['max_hp' => 7000], 'required_level' => 95, 'description' => '恢复7000点生命值'],

            // 药水 - MP药水
            ['name' => '小型魔法药水', 'type' => 'potion', 'sub_type' => 'mp', 'base_stats' => ['max_mana' => 20], 'required_level' => 1, 'description' => '恢复20点魔法值'],
            ['name' => '中型魔法药水', 'type' => 'potion', 'sub_type' => 'mp', 'base_stats' => ['max_mana' => 40], 'required_level' => 5, 'description' => '恢复40点魔法值'],
            ['name' => '大型魔法药水', 'type' => 'potion', 'sub_type' => 'mp', 'base_stats' => ['max_mana' => 80], 'required_level' => 10, 'description' => '恢复80点魔法值'],
            ['name' => '超级魔法药水', 'type' => 'potion', 'sub_type' => 'mp', 'base_stats' => ['max_mana' => 150], 'required_level' => 15, 'description' => '恢复150点魔法值'],
            ['name' => '巨型魔法药水', 'type' => 'potion', 'sub_type' => 'mp', 'base_stats' => ['max_mana' => 250], 'required_level' => 20, 'description' => '恢复250点魔法值'],
            ['name' => '终极魔法药水', 'type' => 'potion', 'sub_type' => 'mp', 'base_stats' => ['max_mana' => 400], 'required_level' => 25, 'description' => '恢复400点魔法值'],
            ['name' => '神话魔法药水', 'type' => 'potion', 'sub_type' => 'mp', 'base_stats' => ['max_mana' => 600], 'required_level' => 35, 'description' => '恢复600点魔法值'],
            ['name' => '神圣魔法药水', 'type' => 'potion', 'sub_type' => 'mp', 'base_stats' => ['max_mana' => 900], 'required_level' => 45, 'description' => '恢复900点魔法值'],
            ['name' => '天使魔法药水', 'type' => 'potion', 'sub_type' => 'mp', 'base_stats' => ['max_mana' => 1300], 'required_level' => 55, 'description' => '恢复1300点魔法值'],
            ['name' => '神之魔法药水', 'type' => 'potion', 'sub_type' => 'mp', 'base_stats' => ['max_mana' => 1800], 'required_level' => 65, 'description' => '恢复1800点魔法值'],
            ['name' => '创世魔法药水', 'type' => 'potion', 'sub_type' => 'mp', 'base_stats' => ['max_mana' => 2500], 'required_level' => 75, 'description' => '恢复2500点魔法值'],
            ['name' => '永恒魔法药水', 'type' => 'potion', 'sub_type' => 'mp', 'base_stats' => ['max_mana' => 3500], 'required_level' => 85, 'description' => '恢复3500点魔法值'],
            ['name' => '混沌魔法药水', 'type' => 'potion', 'sub_type' => 'mp', 'base_stats' => ['max_mana' => 5000], 'required_level' => 95, 'description' => '恢复5000点魔法值'],

            // 武器 - 战士
            ['name' => '新手剑', 'type' => 'weapon', 'sub_type' => 'sword', 'base_stats' => ['attack' => 5], 'required_level' => 1],
            ['name' => '铁剑', 'type' => 'weapon', 'sub_type' => 'sword', 'base_stats' => ['attack' => 15], 'required_level' => 5],
            ['name' => '精钢剑', 'type' => 'weapon', 'sub_type' => 'sword', 'base_stats' => ['attack' => 30], 'required_level' => 10],
            ['name' => '符文剑', 'type' => 'weapon', 'sub_type' => 'sword', 'base_stats' => ['attack' => 50, 'crit_rate' => 0.05], 'required_level' => 15],
            ['name' => '龙牙剑', 'type' => 'weapon', 'sub_type' => 'sword', 'base_stats' => ['attack' => 80, 'crit_damage' => 0.3], 'required_level' => 20],
            ['name' => '泰坦之剑', 'type' => 'weapon', 'sub_type' => 'sword', 'base_stats' => ['attack' => 120, 'max_hp' => 100], 'required_level' => 25],
            ['name' => '圣骑士剑', 'type' => 'weapon', 'sub_type' => 'sword', 'base_stats' => ['attack' => 160, 'defense' => 30, 'max_hp' => 150], 'required_level' => 35],
            ['name' => '狂战士之剑', 'type' => 'weapon', 'sub_type' => 'sword', 'base_stats' => ['attack' => 220, 'crit_damage' => 0.5, 'strength' => 25], 'required_level' => 45],
            ['name' => '天使之剑', 'type' => 'weapon', 'sub_type' => 'sword', 'base_stats' => ['attack' => 300, 'crit_rate' => 0.15, 'max_hp' => 300], 'required_level' => 55],
            ['name' => '神圣审判剑', 'type' => 'weapon', 'sub_type' => 'sword', 'base_stats' => ['attack' => 400, 'crit_damage' => 0.8, 'all_stats' => 15], 'required_level' => 65],
            ['name' => '神王之剑', 'type' => 'weapon', 'sub_type' => 'sword', 'base_stats' => ['attack' => 550, 'max_hp' => 800, 'crit_rate' => 0.2, 'crit_damage' => 1.0], 'required_level' => 75],
            ['name' => '永恒之刃', 'type' => 'weapon', 'sub_type' => 'sword', 'base_stats' => ['attack' => 700, 'all_stats' => 30, 'crit_damage' => 1.2], 'required_level' => 85],
            ['name' => '混沌斩裂者', 'type' => 'weapon', 'sub_type' => 'sword', 'base_stats' => ['attack' => 900, 'crit_rate' => 0.25, 'crit_damage' => 1.5, 'max_hp' => 1500], 'required_level' => 95],

            // 武器 - 法师
            ['name' => '新手法杖', 'type' => 'weapon', 'sub_type' => 'staff', 'base_stats' => ['attack' => 3, 'max_mana' => 20], 'required_level' => 1],
            ['name' => '橡木法杖', 'type' => 'weapon', 'sub_type' => 'staff', 'base_stats' => ['attack' => 10, 'max_mana' => 50], 'required_level' => 5],
            ['name' => '水晶法杖', 'type' => 'weapon', 'sub_type' => 'staff', 'base_stats' => ['attack' => 25, 'max_mana' => 100], 'required_level' => 10],
            ['name' => '月亮法杖', 'type' => 'weapon', 'sub_type' => 'staff', 'base_stats' => ['attack' => 45, 'max_mana' => 200], 'required_level' => 15],
            ['name' => '星辰法杖', 'type' => 'weapon', 'sub_type' => 'staff', 'base_stats' => ['attack' => 70, 'max_mana' => 350, 'crit_rate' => 0.08], 'required_level' => 20],
            ['name' => '虚空法杖', 'type' => 'weapon', 'sub_type' => 'staff', 'base_stats' => ['attack' => 100, 'max_mana' => 500], 'required_level' => 25],
            ['name' => '奥术法杖', 'type' => 'weapon', 'sub_type' => 'staff', 'base_stats' => ['attack' => 140, 'max_mana' => 700, 'energy' => 20], 'required_level' => 35],
            ['name' => '凤凰法杖', 'type' => 'weapon', 'sub_type' => 'staff', 'base_stats' => ['attack' => 200, 'max_mana' => 1000, 'crit_damage' => 0.4], 'required_level' => 45],
            ['name' => '天使法杖', 'type' => 'weapon', 'sub_type' => 'staff', 'base_stats' => ['attack' => 280, 'max_mana' => 1500, 'crit_rate' => 0.12], 'required_level' => 55],
            ['name' => '大魔导师法杖', 'type' => 'weapon', 'sub_type' => 'staff', 'base_stats' => ['attack' => 380, 'max_mana' => 2200, 'energy' => 40], 'required_level' => 65],
            ['name' => '神之启示法杖', 'type' => 'weapon', 'sub_type' => 'staff', 'base_stats' => ['attack' => 500, 'max_mana' => 3000, 'crit_rate' => 0.18, 'all_stats' => 20], 'required_level' => 75],
            ['name' => '永恒魔力源', 'type' => 'weapon', 'sub_type' => 'staff', 'base_stats' => ['attack' => 650, 'max_mana' => 4000, 'crit_damage' => 1.0], 'required_level' => 85],
            ['name' => '混沌魔杖', 'type' => 'weapon', 'sub_type' => 'staff', 'base_stats' => ['attack' => 850, 'max_mana' => 5500, 'crit_rate' => 0.22, 'energy' => 60], 'required_level' => 95],

            // 武器 - 游侠
            ['name' => '新手弓', 'type' => 'weapon', 'sub_type' => 'bow', 'base_stats' => ['attack' => 4, 'crit_rate' => 0.02], 'required_level' => 1],
            ['name' => '长弓', 'type' => 'weapon', 'sub_type' => 'bow', 'base_stats' => ['attack' => 12, 'crit_rate' => 0.05], 'required_level' => 5],
            ['name' => '精灵弓', 'type' => 'weapon', 'sub_type' => 'bow', 'base_stats' => ['attack' => 28, 'crit_rate' => 0.1], 'required_level' => 10],
            ['name' => '猎魔弓', 'type' => 'weapon', 'sub_type' => 'bow', 'base_stats' => ['attack' => 48, 'crit_rate' => 0.15, 'crit_damage' => 0.25], 'required_level' => 15],
            ['name' => '暗影之弓', 'type' => 'weapon', 'sub_type' => 'bow', 'base_stats' => ['attack' => 75, 'crit_rate' => 0.2, 'dexterity' => 15], 'required_level' => 20],
            ['name' => '风神之弓', 'type' => 'weapon', 'sub_type' => 'bow', 'base_stats' => ['attack' => 110, 'crit_rate' => 0.25, 'crit_damage' => 0.5], 'required_level' => 25],
            ['name' => '精灵王之弓', 'type' => 'weapon', 'sub_type' => 'bow', 'base_stats' => ['attack' => 150, 'crit_rate' => 0.28, 'crit_damage' => 0.6, 'dexterity' => 25], 'required_level' => 35],
            ['name' => '凤凰羽弓', 'type' => 'weapon', 'sub_type' => 'bow', 'base_stats' => ['attack' => 210, 'crit_rate' => 0.32, 'crit_damage' => 0.8], 'required_level' => 45],
            ['name' => '天使之弓', 'type' => 'weapon', 'sub_type' => 'bow', 'base_stats' => ['attack' => 290, 'crit_rate' => 0.35, 'crit_damage' => 1.0, 'dexterity' => 40], 'required_level' => 55],
            ['name' => '神射手之弓', 'type' => 'weapon', 'sub_type' => 'bow', 'base_stats' => ['attack' => 390, 'crit_rate' => 0.4, 'crit_damage' => 1.2], 'required_level' => 65],
            ['name' => '神之狩猎者', 'type' => 'weapon', 'sub_type' => 'bow', 'base_stats' => ['attack' => 520, 'crit_rate' => 0.45, 'crit_damage' => 1.5, 'all_stats' => 20], 'required_level' => 75],
            ['name' => '永恒追猎者', 'type' => 'weapon', 'sub_type' => 'bow', 'base_stats' => ['attack' => 680, 'crit_rate' => 0.5, 'crit_damage' => 1.8], 'required_level' => 85],
            ['name' => '混沌穿刺者', 'type' => 'weapon', 'sub_type' => 'bow', 'base_stats' => ['attack' => 880, 'crit_rate' => 0.55, 'crit_damage' => 2.2, 'dexterity' => 80], 'required_level' => 95],

            // 头盔
            ['name' => '布帽', 'type' => 'helmet', 'sub_type' => 'cloth', 'base_stats' => ['defense' => 2, 'max_hp' => 10], 'required_level' => 1],
            ['name' => '皮帽', 'type' => 'helmet', 'sub_type' => 'leather', 'base_stats' => ['defense' => 5, 'max_hp' => 20], 'required_level' => 5],
            ['name' => '铁盔', 'type' => 'helmet', 'sub_type' => 'plate', 'base_stats' => ['defense' => 15, 'max_hp' => 50], 'required_level' => 10],
            ['name' => '秘银头盔', 'type' => 'helmet', 'sub_type' => 'plate', 'base_stats' => ['defense' => 25, 'max_hp' => 80, 'defense' => 5], 'required_level' => 15],
            ['name' => '龙骨头盔', 'type' => 'helmet', 'sub_type' => 'plate', 'base_stats' => ['defense' => 40, 'max_hp' => 150], 'required_level' => 20],
            ['name' => '神圣头盔', 'type' => 'helmet', 'sub_type' => 'plate', 'base_stats' => ['defense' => 60, 'max_hp' => 250, 'max_mana' => 50], 'required_level' => 25],
            ['name' => '圣骑士头盔', 'type' => 'helmet', 'sub_type' => 'plate', 'base_stats' => ['defense' => 90, 'max_hp' => 400, 'vitality' => 20], 'required_level' => 35],
            ['name' => '天使头盔', 'type' => 'helmet', 'sub_type' => 'plate', 'base_stats' => ['defense' => 130, 'max_hp' => 600, 'all_stats' => 10], 'required_level' => 45],
            ['name' => '神之头盔', 'type' => 'helmet', 'sub_type' => 'plate', 'base_stats' => ['defense' => 180, 'max_hp' => 900, 'vitality' => 40], 'required_level' => 55],
            ['name' => '永恒头盔', 'type' => 'helmet', 'sub_type' => 'plate', 'base_stats' => ['defense' => 250, 'max_hp' => 1300, 'all_stats' => 20], 'required_level' => 65],
            ['name' => '混沌头盔', 'type' => 'helmet', 'sub_type' => 'plate', 'base_stats' => ['defense' => 350, 'max_hp' => 2000, 'vitality' => 60], 'required_level' => 75],
            ['name' => '创世头盔', 'type' => 'helmet', 'sub_type' => 'plate', 'base_stats' => ['defense' => 480, 'max_hp' => 3000, 'all_stats' => 35], 'required_level' => 85],
            ['name' => '神王头盔', 'type' => 'helmet', 'sub_type' => 'plate', 'base_stats' => ['defense' => 650, 'max_hp' => 4500, 'vitality' => 100], 'required_level' => 95],

            // 盔甲
            ['name' => '布衣', 'type' => 'armor', 'sub_type' => 'cloth', 'base_stats' => ['defense' => 5, 'max_hp' => 20], 'required_level' => 1],
            ['name' => '皮甲', 'type' => 'armor', 'sub_type' => 'leather', 'base_stats' => ['defense' => 12, 'max_hp' => 40], 'required_level' => 5],
            ['name' => '锁子甲', 'type' => 'armor', 'sub_type' => 'mail', 'base_stats' => ['defense' => 25, 'max_hp' => 80], 'required_level' => 10],
            ['name' => '板甲', 'type' => 'armor', 'sub_type' => 'plate', 'base_stats' => ['defense' => 40, 'max_hp' => 120], 'required_level' => 15],
            ['name' => '秘银甲', 'type' => 'armor', 'sub_type' => 'plate', 'base_stats' => ['defense' => 65, 'max_hp' => 200], 'required_level' => 20],
            ['name' => '龙鳞甲', 'type' => 'armor', 'sub_type' => 'plate', 'base_stats' => ['defense' => 100, 'max_hp' => 350, 'attack' => 20], 'required_level' => 25],
            ['name' => '神之铠甲', 'type' => 'armor', 'sub_type' => 'plate', 'base_stats' => ['defense' => 150, 'max_hp' => 500, 'defense' => 20], 'required_level' => 30],
            ['name' => '圣骑士铠甲', 'type' => 'armor', 'sub_type' => 'plate', 'base_stats' => ['defense' => 220, 'max_hp' => 800, 'vitality' => 30], 'required_level' => 40],
            ['name' => '天使铠甲', 'type' => 'armor', 'sub_type' => 'plate', 'base_stats' => ['defense' => 300, 'max_hp' => 1200, 'all_stats' => 15], 'required_level' => 50],
            ['name' => '神圣铠甲', 'type' => 'armor', 'sub_type' => 'plate', 'base_stats' => ['defense' => 400, 'max_hp' => 1800, 'vitality' => 50], 'required_level' => 60],
            ['name' => '永恒铠甲', 'type' => 'armor', 'sub_type' => 'plate', 'base_stats' => ['defense' => 550, 'max_hp' => 2600, 'all_stats' => 25], 'required_level' => 70],
            ['name' => '混沌铠甲', 'type' => 'armor', 'sub_type' => 'plate', 'base_stats' => ['defense' => 750, 'max_hp' => 4000, 'vitality' => 80], 'required_level' => 80],
            ['name' => '创世铠甲', 'type' => 'armor', 'sub_type' => 'plate', 'base_stats' => ['defense' => 1000, 'max_hp' => 6000, 'all_stats' => 40], 'required_level' => 90],
            ['name' => '神王铠甲', 'type' => 'armor', 'sub_type' => 'plate', 'base_stats' => ['defense' => 1400, 'max_hp' => 9000, 'vitality' => 120, 'defense' => 200], 'required_level' => 100],

            // 手套
            ['name' => '布手套', 'type' => 'gloves', 'sub_type' => 'cloth', 'base_stats' => ['defense' => 1], 'required_level' => 1],
            ['name' => '皮手套', 'type' => 'gloves', 'sub_type' => 'leather', 'base_stats' => ['defense' => 3, 'crit_rate' => 0.02], 'required_level' => 5],
            ['name' => '铁手套', 'type' => 'gloves', 'sub_type' => 'plate', 'base_stats' => ['defense' => 8, 'attack' => 3], 'required_level' => 10],
            ['name' => '精钢手套', 'type' => 'gloves', 'sub_type' => 'plate', 'base_stats' => ['defense' => 15, 'attack' => 8, 'crit_rate' => 0.03], 'required_level' => 15],
            ['name' => '龙皮手套', 'type' => 'gloves', 'sub_type' => 'plate', 'base_stats' => ['defense' => 25, 'attack' => 15], 'required_level' => 20],
            ['name' => '力量手套', 'type' => 'gloves', 'sub_type' => 'plate', 'base_stats' => ['attack' => 30, 'strength' => 10], 'required_level' => 25],
            ['name' => '圣骑士手套', 'type' => 'gloves', 'sub_type' => 'plate', 'base_stats' => ['defense' => 40, 'attack' => 40, 'strength' => 15], 'required_level' => 35],
            ['name' => '天使手套', 'type' => 'gloves', 'sub_type' => 'plate', 'base_stats' => ['attack' => 60, 'crit_rate' => 0.1, 'all_stats' => 10], 'required_level' => 45],
            ['name' => '神之手套', 'type' => 'gloves', 'sub_type' => 'plate', 'base_stats' => ['attack' => 90, 'defense' => 60, 'crit_damage' => 0.4], 'required_level' => 55],
            ['name' => '永恒手套', 'type' => 'gloves', 'sub_type' => 'plate', 'base_stats' => ['attack' => 130, 'crit_rate' => 0.15, 'all_stats' => 20], 'required_level' => 65],
            ['name' => '混沌手套', 'type' => 'gloves', 'sub_type' => 'plate', 'base_stats' => ['attack' => 180, 'crit_damage' => 0.6, 'strength' => 50], 'required_level' => 75],
            ['name' => '创世手套', 'type' => 'gloves', 'sub_type' => 'plate', 'base_stats' => ['attack' => 250, 'crit_rate' => 0.2, 'all_stats' => 35], 'required_level' => 85],
            ['name' => '神王手套', 'type' => 'gloves', 'sub_type' => 'plate', 'base_stats' => ['attack' => 350, 'crit_damage' => 1.0, 'strength' => 80], 'required_level' => 95],

            // 靴子
            ['name' => '布鞋', 'type' => 'boots', 'sub_type' => 'cloth', 'base_stats' => ['defense' => 1], 'required_level' => 1],
            ['name' => '皮靴', 'type' => 'boots', 'sub_type' => 'leather', 'base_stats' => ['defense' => 3, 'dexterity' => 2], 'required_level' => 5],
            ['name' => '铁靴', 'type' => 'boots', 'sub_type' => 'plate', 'base_stats' => ['defense' => 8], 'required_level' => 10],
            ['name' => '迅捷之靴', 'type' => 'boots', 'sub_type' => 'plate', 'base_stats' => ['defense' => 12, 'dexterity' => 8, 'crit_rate' => 0.05], 'required_level' => 15],
            ['name' => '游侠之靴', 'type' => 'boots', 'sub_type' => 'leather', 'base_stats' => ['defense' => 20, 'dexterity' => 15], 'required_level' => 20],
            ['name' => '幻影之靴', 'type' => 'boots', 'sub_type' => 'leather', 'base_stats' => ['defense' => 30, 'dexterity' => 25, 'crit_damage' => 0.15], 'required_level' => 25],
            ['name' => '圣骑士靴', 'type' => 'boots', 'sub_type' => 'plate', 'base_stats' => ['defense' => 45, 'dexterity' => 30, 'max_hp' => 150], 'required_level' => 35],
            ['name' => '天使之靴', 'type' => 'boots', 'sub_type' => 'leather', 'base_stats' => ['defense' => 35, 'dexterity' => 45, 'crit_rate' => 0.12], 'required_level' => 45],
            ['name' => '神速之靴', 'type' => 'boots', 'sub_type' => 'leather', 'base_stats' => ['defense' => 50, 'dexterity' => 65, 'crit_damage' => 0.3], 'required_level' => 55],
            ['name' => '永恒之靴', 'type' => 'boots', 'sub_type' => 'leather', 'base_stats' => ['defense' => 70, 'dexterity' => 90, 'crit_rate' => 0.18], 'required_level' => 65],
            ['name' => '混沌之靴', 'type' => 'boots', 'sub_type' => 'leather', 'base_stats' => ['defense' => 100, 'dexterity' => 120, 'crit_damage' => 0.5], 'required_level' => 75],
            ['name' => '创世之靴', 'type' => 'boots', 'sub_type' => 'leather', 'base_stats' => ['defense' => 140, 'dexterity' => 160, 'crit_rate' => 0.25], 'required_level' => 85],
            ['name' => '神王之靴', 'type' => 'boots', 'sub_type' => 'leather', 'base_stats' => ['defense' => 200, 'dexterity' => 220, 'crit_damage' => 0.8, 'all_stats' => 30], 'required_level' => 95],

            // 腰带
            ['name' => '布腰带', 'type' => 'belt', 'sub_type' => 'cloth', 'base_stats' => ['max_hp' => 10], 'required_level' => 1],
            ['name' => '皮带', 'type' => 'belt', 'sub_type' => 'leather', 'base_stats' => ['max_hp' => 25, 'defense' => 2], 'required_level' => 5],
            ['name' => '铁腰带', 'type' => 'belt', 'sub_type' => 'plate', 'base_stats' => ['max_hp' => 50, 'defense' => 5], 'required_level' => 10],
            ['name' => '巨人腰带', 'type' => 'belt', 'sub_type' => 'leather', 'base_stats' => ['max_hp' => 100, 'vitality' => 10], 'required_level' => 15],
            ['name' => '生命腰带', 'type' => 'belt', 'sub_type' => 'leather', 'base_stats' => ['max_hp' => 200, 'vitality' => 20], 'required_level' => 20],
            ['name' => '泰坦腰带', 'type' => 'belt', 'sub_type' => 'leather', 'base_stats' => ['max_hp' => 350, 'vitality' => 35, 'strength' => 15], 'required_level' => 25],
            ['name' => '圣骑士腰带', 'type' => 'belt', 'sub_type' => 'leather', 'base_stats' => ['max_hp' => 550, 'vitality' => 45, 'defense' => 30], 'required_level' => 35],
            ['name' => '天使腰带', 'type' => 'belt', 'sub_type' => 'leather', 'base_stats' => ['max_hp' => 850, 'vitality' => 60, 'all_stats' => 10], 'required_level' => 45],
            ['name' => '神之腰带', 'type' => 'belt', 'sub_type' => 'leather', 'base_stats' => ['max_hp' => 1200, 'vitality' => 80, 'strength' => 40], 'required_level' => 55],
            ['name' => '永恒腰带', 'type' => 'belt', 'sub_type' => 'leather', 'base_stats' => ['max_hp' => 1700, 'vitality' => 100, 'all_stats' => 20], 'required_level' => 65],
            ['name' => '混沌腰带', 'type' => 'belt', 'sub_type' => 'leather', 'base_stats' => ['max_hp' => 2500, 'vitality' => 130, 'strength' => 60], 'required_level' => 75],
            ['name' => '创世腰带', 'type' => 'belt', 'sub_type' => 'leather', 'base_stats' => ['max_hp' => 3500, 'vitality' => 170, 'all_stats' => 35], 'required_level' => 85],
            ['name' => '神王腰带', 'type' => 'belt', 'sub_type' => 'leather', 'base_stats' => ['max_hp' => 5000, 'vitality' => 220, 'strength' => 100], 'required_level' => 95],

            // 戒指
            ['name' => '铜戒指', 'type' => 'ring', 'base_stats' => ['attack' => 2], 'required_level' => 1],
            ['name' => '银戒指', 'type' => 'ring', 'base_stats' => ['attack' => 5, 'crit_rate' => 0.02], 'required_level' => 5],
            ['name' => '金戒指', 'type' => 'ring', 'base_stats' => ['attack' => 10, 'crit_rate' => 0.05], 'required_level' => 10],
            ['name' => '红宝石戒指', 'type' => 'ring', 'base_stats' => ['attack' => 15, 'crit_damage' => 0.2], 'required_level' => 15],
            ['name' => '蓝宝石戒指', 'type' => 'ring', 'base_stats' => ['max_mana' => 100, 'attack' => 12], 'required_level' => 15],
            ['name' => '翡翠戒指', 'type' => 'ring', 'base_stats' => ['max_hp' => 100, 'defense' => 10], 'required_level' => 15],
            ['name' => '乌鸦戒指', 'type' => 'ring', 'base_stats' => ['attack' => 25, 'crit_rate' => 0.1, 'crit_damage' => 0.3], 'required_level' => 20],
            ['name' => '乔丹之石', 'type' => 'ring', 'base_stats' => ['attack' => 30, 'max_mana' => 150, 'all_stats' => 5], 'required_level' => 25],
            ['name' => '矮人戒指', 'type' => 'ring', 'base_stats' => ['defense' => 30, 'max_hp' => 200], 'required_level' => 25],
            ['name' => '魔法戒指', 'type' => 'ring', 'base_stats' => ['max_mana' => 300, 'energy' => 20], 'required_level' => 25],
            ['name' => '圣骑士戒指', 'type' => 'ring', 'base_stats' => ['attack' => 45, 'defense' => 25, 'all_stats' => 10], 'required_level' => 35],
            ['name' => '天使之戒', 'type' => 'ring', 'base_stats' => ['attack' => 65, 'crit_rate' => 0.12, 'max_hp' => 300], 'required_level' => 45],
            ['name' => '神之戒指', 'type' => 'ring', 'base_stats' => ['attack' => 90, 'crit_damage' => 0.5, 'all_stats' => 15], 'required_level' => 55],
            ['name' => '永恒之戒', 'type' => 'ring', 'base_stats' => ['attack' => 120, 'crit_rate' => 0.18, 'crit_damage' => 0.6], 'required_level' => 65],
            ['name' => '混沌之戒', 'type' => 'ring', 'base_stats' => ['attack' => 160, 'all_stats' => 25, 'max_hp' => 600], 'required_level' => 75],
            ['name' => '创世之戒', 'type' => 'ring', 'base_stats' => ['attack' => 220, 'crit_rate' => 0.22, 'crit_damage' => 0.8], 'required_level' => 85],
            ['name' => '神王之戒', 'type' => 'ring', 'base_stats' => ['attack' => 300, 'all_stats' => 40, 'crit_damage' => 1.0], 'required_level' => 95],

            // 护身符
            ['name' => '木制护符', 'type' => 'amulet', 'base_stats' => ['max_hp' => 15], 'required_level' => 1],
            ['name' => '骨制护符', 'type' => 'amulet', 'base_stats' => ['max_hp' => 30, 'max_mana' => 15], 'required_level' => 5],
            ['name' => '水晶护符', 'type' => 'amulet', 'base_stats' => ['max_hp' => 50, 'max_mana' => 50, 'defense' => 5], 'required_level' => 10],
            ['name' => '狮子护符', 'type' => 'amulet', 'base_stats' => ['attack' => 20, 'defense' => 15, 'max_hp' => 80], 'required_level' => 15],
            ['name' => '猫眼护符', 'type' => 'amulet', 'base_stats' => ['crit_rate' => 0.15, 'crit_damage' => 0.3, 'dexterity' => 15], 'required_level' => 15],
            ['name' => 'marshal护符', 'type' => 'amulet', 'base_stats' => ['all_stats' => 10, 'max_hp' => 100, 'max_mana' => 100], 'required_level' => 20],
            ['name' => '地狱护符', 'type' => 'amulet', 'base_stats' => ['attack' => 40, 'crit_damage' => 0.5, 'strength' => 20], 'required_level' => 25],
            ['name' => '神圣护符', 'type' => 'amulet', 'base_stats' => ['max_hp' => 300, 'max_mana' => 300, 'all_stats' => 20], 'required_level' => 30],
            ['name' => '圣骑士护符', 'type' => 'amulet', 'base_stats' => ['attack' => 60, 'defense' => 40, 'max_hp' => 400], 'required_level' => 40],
            ['name' => '天使护符', 'type' => 'amulet', 'base_stats' => ['all_stats' => 25, 'max_hp' => 500, 'max_mana' => 500], 'required_level' => 50],
            ['name' => '神圣天使护符', 'type' => 'amulet', 'base_stats' => ['attack' => 100, 'crit_damage' => 0.6, 'all_stats' => 30], 'required_level' => 60],
            ['name' => '永恒护符', 'type' => 'amulet', 'base_stats' => ['max_hp' => 1000, 'max_mana' => 1000, 'all_stats' => 35], 'required_level' => 70],
            ['name' => '混沌护符', 'type' => 'amulet', 'base_stats' => ['attack' => 180, 'crit_rate' => 0.2, 'crit_damage' => 0.8], 'required_level' => 80],
            ['name' => '创世护符', 'type' => 'amulet', 'base_stats' => ['all_stats' => 50, 'max_hp' => 2000, 'max_mana' => 2000], 'required_level' => 90],
            ['name' => '神王护符', 'type' => 'amulet', 'base_stats' => ['attack' => 300, 'all_stats' => 60, 'crit_damage' => 1.2], 'required_level' => 100],
        ];

        foreach ($items as $item) {
            GameItemDefinition::create(array_merge($item, [
                'icon' => $item['type'].'.png',
                'is_active' => true,
            ]));
        }
    }

    private function seedSkillDefinitions(): void
    {
        $skills = [
            // 战士技能
            ['name' => '重击', 'description' => '强力一击，造成额外伤害', 'type' => 'active', 'class_restriction' => 'warrior', 'base_damage' => 20, 'damage_per_level' => 10, 'mana_cost' => 10, 'mana_cost_per_level' => 2, 'cooldown' => 3],
            ['name' => '战吼', 'description' => '发出怒吼，提升攻击力', 'type' => 'active', 'class_restriction' => 'warrior', 'base_damage' => 0, 'damage_per_level' => 0, 'mana_cost' => 15, 'mana_cost_per_level' => 3, 'cooldown' => 10, 'effects' => ['buff_attack' => 10, 'duration' => 5]],
            ['name' => '铁壁', 'description' => '被动提升防御力', 'type' => 'passive', 'class_restriction' => 'warrior', 'base_damage' => 0, 'damage_per_level' => 0, 'mana_cost' => 0, 'mana_cost_per_level' => 0, 'cooldown' => 0, 'effects' => ['defense_bonus' => 5]],
            ['name' => '旋风斩', 'description' => '旋转攻击周围所有敌人', 'type' => 'active', 'class_restriction' => 'warrior', 'base_damage' => 35, 'damage_per_level' => 15, 'mana_cost' => 25, 'mana_cost_per_level' => 4, 'cooldown' => 6],
            ['name' => '狂暴', 'description' => '进入狂暴状态，大幅提升攻击力', 'type' => 'active', 'class_restriction' => 'warrior', 'base_damage' => 0, 'damage_per_level' => 0, 'mana_cost' => 40, 'mana_cost_per_level' => 5, 'cooldown' => 30, 'effects' => ['buff_attack' => 50, 'duration' => 10]],
            ['name' => '钢铁之躯', 'description' => '被动提升生命值和防御力', 'type' => 'passive', 'class_restriction' => 'warrior', 'base_damage' => 0, 'damage_per_level' => 0, 'mana_cost' => 0, 'mana_cost_per_level' => 0, 'cooldown' => 0, 'effects' => ['hp_bonus' => 100, 'defense_bonus' => 10]],
            ['name' => '斩杀', 'description' => '对低血量敌人造成巨额伤害', 'type' => 'active', 'class_restriction' => 'warrior', 'base_damage' => 50, 'damage_per_level' => 25, 'mana_cost' => 30, 'mana_cost_per_level' => 5, 'cooldown' => 8, 'effects' => ['execute_threshold' => 0.3, 'execute_multiplier' => 2.0]],

            // 法师技能
            ['name' => '火球术', 'description' => '发射火球，造成魔法伤害', 'type' => 'active', 'class_restriction' => 'mage', 'base_damage' => 30, 'damage_per_level' => 15, 'mana_cost' => 15, 'mana_cost_per_level' => 3, 'cooldown' => 2],
            ['name' => '冰霜新星', 'description' => '释放冰霜，造成范围伤害', 'type' => 'active', 'class_restriction' => 'mage', 'base_damage' => 25, 'damage_per_level' => 12, 'mana_cost' => 20, 'mana_cost_per_level' => 4, 'cooldown' => 5],
            ['name' => '魔力涌动', 'description' => '被动提升法力上限', 'type' => 'passive', 'class_restriction' => 'mage', 'base_damage' => 0, 'damage_per_level' => 0, 'mana_cost' => 0, 'mana_cost_per_level' => 0, 'cooldown' => 0, 'effects' => ['mana_bonus' => 20]],
            ['name' => '雷击', 'description' => '召唤闪电，瞬间造成高额伤害', 'type' => 'active', 'class_restriction' => 'mage', 'base_damage' => 45, 'damage_per_level' => 20, 'mana_cost' => 25, 'mana_cost_per_level' => 5, 'cooldown' => 4],
            ['name' => '魔法护盾', 'description' => '用法力值吸收伤害', 'type' => 'active', 'class_restriction' => 'mage', 'base_damage' => 0, 'damage_per_level' => 0, 'mana_cost' => 35, 'mana_cost_per_level' => 6, 'cooldown' => 15, 'effects' => ['shield' => 100, 'duration' => 8]],
            ['name' => '奥术智慧', 'description' => '被动提升魔法攻击力', 'type' => 'passive', 'class_restriction' => 'mage', 'base_damage' => 0, 'damage_per_level' => 0, 'mana_cost' => 0, 'mana_cost_per_level' => 0, 'cooldown' => 0, 'effects' => ['spell_power_bonus' => 15]],
            ['name' => '陨石术', 'description' => '召唤陨石从天而降', 'type' => 'active', 'class_restriction' => 'mage', 'base_damage' => 80, 'damage_per_level' => 30, 'mana_cost' => 60, 'mana_cost_per_level' => 8, 'cooldown' => 12],
            ['name' => '法力燃烧', 'description' => '燃烧敌人法力并造成伤害', 'type' => 'active', 'class_restriction' => 'mage', 'base_damage' => 20, 'damage_per_level' => 10, 'mana_cost' => 15, 'mana_cost_per_level' => 3, 'cooldown' => 6, 'effects' => ['mana_burn' => 50]],

            // 游侠技能
            ['name' => '穿刺射击', 'description' => '精准射击，高暴击', 'type' => 'active', 'class_restriction' => 'ranger', 'base_damage' => 25, 'damage_per_level' => 12, 'mana_cost' => 12, 'mana_cost_per_level' => 2, 'cooldown' => 2, 'effects' => ['crit_bonus' => 0.2]],
            ['name' => '多重射击', 'description' => '同时射出多支箭', 'type' => 'active', 'class_restriction' => 'ranger', 'base_damage' => 15, 'damage_per_level' => 8, 'mana_cost' => 18, 'mana_cost_per_level' => 3, 'cooldown' => 4],
            ['name' => '鹰眼', 'description' => '被动提升暴击率', 'type' => 'passive', 'class_restriction' => 'ranger', 'base_damage' => 0, 'damage_per_level' => 0, 'mana_cost' => 0, 'mana_cost_per_level' => 0, 'cooldown' => 0, 'effects' => ['crit_rate_bonus' => 0.03]],
            ['name' => '毒箭', 'description' => '射出毒箭，持续造成伤害', 'type' => 'active', 'class_restriction' => 'ranger', 'base_damage' => 20, 'damage_per_level' => 10, 'mana_cost' => 15, 'mana_cost_per_level' => 3, 'cooldown' => 5, 'effects' => ['dot' => 15, 'dot_duration' => 5]],
            ['name' => '闪避', 'description' => '提升闪避率', 'type' => 'active', 'class_restriction' => 'ranger', 'base_damage' => 0, 'damage_per_level' => 0, 'mana_cost' => 20, 'mana_cost_per_level' => 4, 'cooldown' => 12, 'effects' => ['dodge_bonus' => 0.3, 'duration' => 6]],
            ['name' => '致命瞄准', 'description' => '被动提升暴击伤害', 'type' => 'passive', 'class_restriction' => 'ranger', 'base_damage' => 0, 'damage_per_level' => 0, 'mana_cost' => 0, 'mana_cost_per_level' => 0, 'cooldown' => 0, 'effects' => ['crit_damage_bonus' => 0.15]],
            ['name' => '箭雨', 'description' => '从天而降的箭雨', 'type' => 'active', 'class_restriction' => 'ranger', 'base_damage' => 40, 'damage_per_level' => 18, 'mana_cost' => 45, 'mana_cost_per_level' => 6, 'cooldown' => 10],
            ['name' => '暗影步', 'description' => '瞬间移动到敌人身后', 'type' => 'active', 'class_restriction' => 'ranger', 'base_damage' => 30, 'damage_per_level' => 15, 'mana_cost' => 25, 'mana_cost_per_level' => 4, 'cooldown' => 8, 'effects' => ['backstab_bonus' => 1.5]],

            // 通用技能
            ['name' => '治疗术', 'description' => '恢复生命值', 'type' => 'active', 'class_restriction' => 'all', 'base_damage' => -30, 'damage_per_level' => -10, 'mana_cost' => 20, 'mana_cost_per_level' => 3, 'cooldown' => 8, 'effects' => ['heal' => true]],
            ['name' => '力量强化', 'description' => '被动提升力量', 'type' => 'passive', 'class_restriction' => 'all', 'base_damage' => 0, 'damage_per_level' => 0, 'mana_cost' => 0, 'mana_cost_per_level' => 0, 'cooldown' => 0, 'effects' => ['strength_bonus' => 2]],
            ['name' => '敏捷强化', 'description' => '被动提升敏捷', 'type' => 'passive', 'class_restriction' => 'all', 'base_damage' => 0, 'damage_per_level' => 0, 'mana_cost' => 0, 'mana_cost_per_level' => 0, 'cooldown' => 0, 'effects' => ['dexterity_bonus' => 2]],
            ['name' => '体力强化', 'description' => '被动提升体力', 'type' => 'passive', 'class_restriction' => 'all', 'base_damage' => 0, 'damage_per_level' => 0, 'mana_cost' => 0, 'mana_cost_per_level' => 0, 'cooldown' => 0, 'effects' => ['vitality_bonus' => 2]],
            ['name' => '能量强化', 'description' => '被动提升能量', 'type' => 'passive', 'class_restriction' => 'all', 'base_damage' => 0, 'damage_per_level' => 0, 'mana_cost' => 0, 'mana_cost_per_level' => 0, 'cooldown' => 0, 'effects' => ['energy_bonus' => 2]],
            ['name' => '吸血', 'description' => '攻击时回复生命值', 'type' => 'passive', 'class_restriction' => 'all', 'base_damage' => 0, 'damage_per_level' => 0, 'mana_cost' => 0, 'mana_cost_per_level' => 0, 'cooldown' => 0, 'effects' => ['life_steal' => 0.05]],
            ['name' => '回蓝', 'description' => '被动手动回复法力值', 'type' => 'passive', 'class_restriction' => 'all', 'base_damage' => 0, 'damage_per_level' => 0, 'mana_cost' => 0, 'mana_cost_per_level' => 0, 'cooldown' => 0, 'effects' => ['mana_regen' => 2]],
        ];

        foreach ($skills as $skill) {
            GameSkillDefinition::create(array_merge($skill, [
                'max_level' => 10,
                'icon' => 'skill_'.strtolower(str_replace(' ', '_', $skill['name'])).'.png',
                'is_active' => true,
            ]));
        }
    }

    private function seedMonsterDefinitions(): void
    {
        $monsters = [
            // 第一幕 - 森林 (1-10级)
            ['name' => '小野猪', 'type' => 'normal', 'level' => 1, 'hp_base' => 50, 'hp_per_level' => 10, 'attack_base' => 5, 'attack_per_level' => 2, 'defense_base' => 2, 'defense_per_level' => 1, 'experience_base' => 10, 'experience_per_level' => 5, 'drop_table' => ['gold_base' => 5, 'gold_range' => 5, 'item_chance' => 0.05]],
            ['name' => '野狼', 'type' => 'normal', 'level' => 2, 'hp_base' => 60, 'hp_per_level' => 12, 'attack_base' => 8, 'attack_per_level' => 2, 'defense_base' => 3, 'defense_per_level' => 1, 'experience_base' => 15, 'experience_per_level' => 6, 'drop_table' => ['gold_base' => 8, 'gold_range' => 7, 'item_chance' => 0.08]],
            ['name' => '森林哥布林', 'type' => 'normal', 'level' => 3, 'hp_base' => 70, 'hp_per_level' => 15, 'attack_base' => 10, 'attack_per_level' => 3, 'defense_base' => 4, 'defense_per_level' => 1, 'experience_base' => 20, 'experience_per_level' => 8, 'drop_table' => ['gold_base' => 10, 'gold_range' => 10, 'item_chance' => 0.1]],
            ['name' => '巨狼', 'type' => 'elite', 'level' => 5, 'hp_base' => 150, 'hp_per_level' => 30, 'attack_base' => 15, 'attack_per_level' => 4, 'defense_base' => 6, 'defense_per_level' => 2, 'experience_base' => 50, 'experience_per_level' => 15, 'drop_table' => ['gold_base' => 30, 'gold_range' => 20, 'item_chance' => 0.25]],
            ['name' => '树人长老', 'type' => 'boss', 'level' => 8, 'hp_base' => 400, 'hp_per_level' => 80, 'attack_base' => 25, 'attack_per_level' => 6, 'defense_base' => 12, 'defense_per_level' => 3, 'experience_base' => 150, 'experience_per_level' => 40, 'drop_table' => ['gold_base' => 100, 'gold_range' => 50, 'item_chance' => 0.8]],
            ['name' => '野猪王', 'type' => 'elite', 'level' => 6, 'hp_base' => 200, 'hp_per_level' => 40, 'attack_base' => 20, 'attack_per_level' => 5, 'defense_base' => 8, 'defense_per_level' => 2, 'experience_base' => 70, 'experience_per_level' => 20, 'drop_table' => ['gold_base' => 40, 'gold_range' => 25, 'item_chance' => 0.3]],

            // 第二幕 - 洞穴 (5-20级)
            ['name' => '蝙蝠', 'type' => 'normal', 'level' => 5, 'hp_base' => 45, 'hp_per_level' => 10, 'attack_base' => 12, 'attack_per_level' => 3, 'defense_base' => 3, 'defense_per_level' => 1, 'experience_base' => 25, 'experience_per_level' => 8, 'drop_table' => ['gold_base' => 12, 'gold_range' => 8, 'item_chance' => 0.08]],
            ['name' => '洞穴蜘蛛', 'type' => 'normal', 'level' => 6, 'hp_base' => 55, 'hp_per_level' => 12, 'attack_base' => 14, 'attack_per_level' => 3, 'defense_base' => 4, 'defense_per_level' => 1, 'experience_base' => 30, 'experience_per_level' => 10, 'drop_table' => ['gold_base' => 15, 'gold_range' => 10, 'item_chance' => 0.1]],
            ['name' => '骷髅兵', 'type' => 'normal', 'level' => 7, 'hp_base' => 80, 'hp_per_level' => 18, 'attack_base' => 16, 'attack_per_level' => 4, 'defense_base' => 8, 'defense_per_level' => 2, 'experience_base' => 40, 'experience_per_level' => 12, 'drop_table' => ['gold_base' => 20, 'gold_range' => 15, 'item_chance' => 0.12]],
            ['name' => '骷髅法师', 'type' => 'elite', 'level' => 10, 'hp_base' => 200, 'hp_per_level' => 40, 'attack_base' => 25, 'attack_per_level' => 5, 'defense_base' => 10, 'defense_per_level' => 3, 'experience_base' => 80, 'experience_per_level' => 20, 'drop_table' => ['gold_base' => 50, 'gold_range' => 30, 'item_chance' => 0.3]],
            ['name' => '骸骨之王', 'type' => 'boss', 'level' => 12, 'hp_base' => 600, 'hp_per_level' => 100, 'attack_base' => 35, 'attack_per_level' => 8, 'defense_base' => 18, 'defense_per_level' => 4, 'experience_base' => 250, 'experience_per_level' => 60, 'drop_table' => ['gold_base' => 200, 'gold_range' => 100, 'item_chance' => 1.0]],
            ['name' => '巨型蜘蛛', 'type' => 'elite', 'level' => 8, 'hp_base' => 180, 'hp_per_level' => 35, 'attack_base' => 22, 'attack_per_level' => 5, 'defense_base' => 9, 'defense_per_level' => 2, 'experience_base' => 65, 'experience_per_level' => 18, 'drop_table' => ['gold_base' => 45, 'gold_range' => 28, 'item_chance' => 0.28]],

            // 第三幕 - 地狱 (12-30级)
            ['name' => '小恶魔', 'type' => 'normal', 'level' => 12, 'hp_base' => 100, 'hp_per_level' => 25, 'attack_base' => 25, 'attack_per_level' => 5, 'defense_base' => 10, 'defense_per_level' => 2, 'experience_base' => 60, 'experience_per_level' => 15, 'drop_table' => ['gold_base' => 30, 'gold_range' => 20, 'item_chance' => 0.15]],
            ['name' => '火焰元素', 'type' => 'normal', 'level' => 14, 'hp_base' => 120, 'hp_per_level' => 30, 'attack_base' => 30, 'attack_per_level' => 6, 'defense_base' => 12, 'defense_per_level' => 3, 'experience_base' => 80, 'experience_per_level' => 20, 'drop_table' => ['gold_base' => 40, 'gold_range' => 25, 'item_chance' => 0.18]],
            ['name' => '地狱骑士', 'type' => 'elite', 'level' => 16, 'hp_base' => 350, 'hp_per_level' => 70, 'attack_base' => 40, 'attack_per_level' => 8, 'defense_base' => 20, 'defense_per_level' => 4, 'experience_base' => 150, 'experience_per_level' => 35, 'drop_table' => ['gold_base' => 80, 'gold_range' => 50, 'item_chance' => 0.4]],
            ['name' => '地狱魔王', 'type' => 'boss', 'level' => 20, 'hp_base' => 1000, 'hp_per_level' => 200, 'attack_base' => 60, 'attack_per_level' => 12, 'defense_base' => 30, 'defense_per_level' => 6, 'experience_base' => 500, 'experience_per_level' => 100, 'drop_table' => ['gold_base' => 500, 'gold_range' => 200, 'item_chance' => 1.0]],
            ['name' => '炎魔', 'type' => 'elite', 'level' => 18, 'hp_base' => 400, 'hp_per_level' => 80, 'attack_base' => 50, 'attack_per_level' => 10, 'defense_base' => 22, 'defense_per_level' => 5, 'experience_base' => 180, 'experience_per_level' => 40, 'drop_table' => ['gold_base' => 100, 'gold_range' => 60, 'item_chance' => 0.45]],
            ['name' => '恶魔巫师', 'type' => 'elite', 'level' => 19, 'hp_base' => 320, 'hp_per_level' => 65, 'attack_base' => 55, 'attack_per_level' => 11, 'defense_base' => 15, 'defense_per_level' => 4, 'experience_base' => 160, 'experience_per_level' => 38, 'drop_table' => ['gold_base' => 90, 'gold_range' => 55, 'item_chance' => 0.42]],

            // 第四幕 - 深渊 (22-40级)
            ['name' => '深渊魔虫', 'type' => 'normal', 'level' => 22, 'hp_base' => 180, 'hp_per_level' => 40, 'attack_base' => 55, 'attack_per_level' => 11, 'defense_base' => 25, 'defense_per_level' => 5, 'experience_base' => 150, 'experience_per_level' => 35, 'drop_table' => ['gold_base' => 80, 'gold_range' => 50, 'item_chance' => 0.22]],
            ['name' => '暗影幽灵', 'type' => 'normal', 'level' => 24, 'hp_base' => 200, 'hp_per_level' => 45, 'attack_base' => 60, 'attack_per_level' => 12, 'defense_base' => 20, 'defense_per_level' => 4, 'experience_base' => 180, 'experience_per_level' => 40, 'drop_table' => ['gold_base' => 100, 'gold_range' => 60, 'item_chance' => 0.25]],
            ['name' => '虚空行者', 'type' => 'elite', 'level' => 26, 'hp_base' => 600, 'hp_per_level' => 120, 'attack_base' => 80, 'attack_per_level' => 16, 'defense_base' => 35, 'defense_per_level' => 7, 'experience_base' => 300, 'experience_per_level' => 70, 'drop_table' => ['gold_base' => 200, 'gold_range' => 120, 'item_chance' => 0.5]],
            ['name' => '深渊领主', 'type' => 'boss', 'level' => 30, 'hp_base' => 2000, 'hp_per_level' => 400, 'attack_base' => 120, 'attack_per_level' => 24, 'defense_base' => 60, 'defense_per_level' => 12, 'experience_base' => 1000, 'experience_per_level' => 200, 'drop_table' => ['gold_base' => 1500, 'gold_range' => 500, 'item_chance' => 1.0]],

            // 第五幕 - 天界 (30-50级)
            ['name' => '天使卫士', 'type' => 'normal', 'level' => 32, 'hp_base' => 300, 'hp_per_level' => 60, 'attack_base' => 90, 'attack_per_level' => 18, 'defense_base' => 45, 'defense_per_level' => 9, 'experience_base' => 300, 'experience_per_level' => 70, 'drop_table' => ['gold_base' => 180, 'gold_range' => 100, 'item_chance' => 0.28]],
            ['name' => '炽天使', 'type' => 'normal', 'level' => 35, 'hp_base' => 350, 'hp_per_level' => 70, 'attack_base' => 100, 'attack_per_level' => 20, 'defense_base' => 50, 'defense_per_level' => 10, 'experience_base' => 350, 'experience_per_level' => 80, 'drop_table' => ['gold_base' => 220, 'gold_range' => 130, 'item_chance' => 0.32]],
            ['name' => '天使长', 'type' => 'elite', 'level' => 38, 'hp_base' => 900, 'hp_per_level' => 180, 'attack_base' => 130, 'attack_per_level' => 26, 'defense_base' => 70, 'defense_per_level' => 14, 'experience_base' => 600, 'experience_per_level' => 140, 'drop_table' => ['gold_base' => 400, 'gold_range' => 250, 'item_chance' => 0.55]],
            ['name' => '堕落天使', 'type' => 'elite', 'level' => 42, 'hp_base' => 1100, 'hp_per_level' => 220, 'attack_base' => 150, 'attack_per_level' => 30, 'defense_base' => 80, 'defense_per_level' => 16, 'experience_base' => 750, 'experience_per_level' => 170, 'drop_table' => ['gold_base' => 500, 'gold_range' => 300, 'item_chance' => 0.6]],
            ['name' => '大天使长', 'type' => 'boss', 'level' => 45, 'hp_base' => 3500, 'hp_per_level' => 700, 'attack_base' => 200, 'attack_per_level' => 40, 'defense_base' => 100, 'defense_per_level' => 20, 'experience_base' => 2000, 'experience_per_level' => 400, 'drop_table' => ['gold_base' => 3000, 'gold_range' => 1000, 'item_chance' => 1.0]],
            ['name' => '圣殿骑士', 'type' => 'elite', 'level' => 40, 'hp_base' => 1000, 'hp_per_level' => 200, 'attack_base' => 140, 'attack_per_level' => 28, 'defense_base' => 90, 'defense_per_level' => 18, 'experience_base' => 680, 'experience_per_level' => 155, 'drop_table' => ['gold_base' => 450, 'gold_range' => 280, 'item_chance' => 0.58]],

            // 第六幕 - 神域 (45-65级)
            ['name' => '神仆', 'type' => 'normal', 'level' => 48, 'hp_base' => 500, 'hp_per_level' => 100, 'attack_base' => 160, 'attack_per_level' => 32, 'defense_base' => 80, 'defense_per_level' => 16, 'experience_base' => 500, 'experience_per_level' => 110, 'drop_table' => ['gold_base' => 350, 'gold_range' => 200, 'item_chance' => 0.35]],
            ['name' => '神使', 'type' => 'normal', 'level' => 52, 'hp_base' => 600, 'hp_per_level' => 120, 'attack_base' => 180, 'attack_per_level' => 36, 'defense_base' => 90, 'defense_per_level' => 18, 'experience_base' => 600, 'experience_per_level' => 130, 'drop_table' => ['gold_base' => 450, 'gold_range' => 250, 'item_chance' => 0.4]],
            ['name' => '神将', 'type' => 'elite', 'level' => 55, 'hp_base' => 1500, 'hp_per_level' => 300, 'attack_base' => 220, 'attack_per_level' => 44, 'defense_base' => 120, 'defense_per_level' => 24, 'experience_base' => 1000, 'experience_per_level' => 220, 'drop_table' => ['gold_base' => 700, 'gold_range' => 400, 'item_chance' => 0.65]],
            ['name' => '神官', 'type' => 'elite', 'level' => 58, 'hp_base' => 1300, 'hp_per_level' => 260, 'attack_base' => 240, 'attack_per_level' => 48, 'defense_base' => 100, 'defense_per_level' => 20, 'experience_base' => 1100, 'experience_per_level' => 240, 'drop_table' => ['gold_base' => 750, 'gold_range' => 450, 'item_chance' => 0.68]],
            ['name' => '神王化身', 'type' => 'boss', 'level' => 60, 'hp_base' => 5000, 'hp_per_level' => 1000, 'attack_base' => 300, 'attack_per_level' => 60, 'defense_base' => 150, 'defense_per_level' => 30, 'experience_base' => 3500, 'experience_per_level' => 700, 'drop_table' => ['gold_base' => 5000, 'gold_range' => 2000, 'item_chance' => 1.0]],
            ['name' => '审判天使', 'type' => 'elite', 'level' => 62, 'hp_base' => 1800, 'hp_per_level' => 360, 'attack_base' => 280, 'attack_per_level' => 56, 'defense_base' => 140, 'defense_per_level' => 28, 'experience_base' => 1300, 'experience_per_level' => 280, 'drop_table' => ['gold_base' => 900, 'gold_range' => 550, 'item_chance' => 0.72]],

            // 第七幕 - 永恒之境 (60-80级)
            ['name' => '永恒守护者', 'type' => 'normal', 'level' => 65, 'hp_base' => 800, 'hp_per_level' => 160, 'attack_base' => 260, 'attack_per_level' => 52, 'defense_base' => 130, 'defense_per_level' => 26, 'experience_base' => 800, 'experience_per_level' => 170, 'drop_table' => ['gold_base' => 600, 'gold_range' => 350, 'item_chance' => 0.45]],
            ['name' => '时空裂隙', 'type' => 'normal', 'level' => 70, 'hp_base' => 1000, 'hp_per_level' => 200, 'attack_base' => 300, 'attack_per_level' => 60, 'defense_base' => 150, 'defense_per_level' => 30, 'experience_base' => 1000, 'experience_per_level' => 210, 'drop_table' => ['gold_base' => 800, 'gold_range' => 450, 'item_chance' => 0.5]],
            ['name' => '永恒战士', 'type' => 'elite', 'level' => 72, 'hp_base' => 2500, 'hp_per_level' => 500, 'attack_base' => 380, 'attack_per_level' => 76, 'defense_base' => 200, 'defense_per_level' => 40, 'experience_base' => 1800, 'experience_per_level' => 380, 'drop_table' => ['gold_base' => 1300, 'gold_range' => 800, 'item_chance' => 0.75]],
            ['name' => '永恒法师', 'type' => 'elite', 'level' => 75, 'hp_base' => 2200, 'hp_per_level' => 440, 'attack_base' => 420, 'attack_per_level' => 84, 'defense_base' => 180, 'defense_per_level' => 36, 'experience_base' => 2000, 'experience_per_level' => 420, 'drop_table' => ['gold_base' => 1500, 'gold_range' => 900, 'item_chance' => 0.78]],
            ['name' => '永恒之王', 'type' => 'boss', 'level' => 78, 'hp_base' => 8000, 'hp_per_level' => 1600, 'attack_base' => 500, 'attack_per_level' => 100, 'defense_base' => 250, 'defense_per_level' => 50, 'experience_base' => 6000, 'experience_per_level' => 1200, 'drop_table' => ['gold_base' => 8000, 'gold_range' => 3000, 'item_chance' => 1.0]],
            ['name' => '永恒骑士', 'type' => 'elite', 'level' => 80, 'hp_base' => 3000, 'hp_per_level' => 600, 'attack_base' => 450, 'attack_per_level' => 90, 'defense_base' => 230, 'defense_per_level' => 46, 'experience_base' => 2400, 'experience_per_level' => 500, 'drop_table' => ['gold_base' => 1800, 'gold_range' => 1100, 'item_chance' => 0.82]],

            // 第八幕 - 混沌虚空 (78-100级)
            ['name' => '混沌精灵', 'type' => 'normal', 'level' => 82, 'hp_base' => 1400, 'hp_per_level' => 280, 'attack_base' => 400, 'attack_per_level' => 80, 'defense_base' => 200, 'defense_per_level' => 40, 'experience_base' => 1400, 'experience_per_level' => 290, 'drop_table' => ['gold_base' => 1100, 'gold_range' => 650, 'item_chance' => 0.55]],
            ['name' => '虚空领主', 'type' => 'normal', 'level' => 85, 'hp_base' => 1800, 'hp_per_level' => 360, 'attack_base' => 450, 'attack_per_level' => 90, 'defense_base' => 220, 'defense_per_level' => 44, 'experience_base' => 1800, 'experience_per_level' => 370, 'drop_table' => ['gold_base' => 1500, 'gold_range' => 850, 'item_chance' => 0.6]],
            ['name' => '混沌战士', 'type' => 'elite', 'level' => 88, 'hp_base' => 4000, 'hp_per_level' => 800, 'attack_base' => 550, 'attack_per_level' => 110, 'defense_base' => 280, 'defense_per_level' => 56, 'experience_base' => 3200, 'experience_per_level' => 650, 'drop_table' => ['gold_base' => 2500, 'gold_range' => 1500, 'item_chance' => 0.85]],
            ['name' => '混沌魔神', 'type' => 'elite', 'level' => 92, 'hp_base' => 5000, 'hp_per_level' => 1000, 'attack_base' => 650, 'attack_per_level' => 130, 'defense_base' => 320, 'defense_per_level' => 64, 'experience_base' => 4000, 'experience_per_level' => 800, 'drop_table' => ['gold_base' => 3200, 'gold_range' => 2000, 'item_chance' => 0.88]],
            ['name' => '混沌之源', 'type' => 'boss', 'level' => 96, 'hp_base' => 15000, 'hp_per_level' => 3000, 'attack_base' => 800, 'attack_per_level' => 160, 'defense_base' => 400, 'defense_per_level' => 80, 'experience_base' => 10000, 'experience_per_level' => 2000, 'drop_table' => ['gold_base' => 12000, 'gold_range' => 5000, 'item_chance' => 1.0]],
            ['name' => '混沌之王', 'type' => 'boss', 'level' => 100, 'hp_base' => 25000, 'hp_per_level' => 5000, 'attack_base' => 1000, 'attack_per_level' => 200, 'defense_base' => 500, 'defense_per_level' => 100, 'experience_base' => 20000, 'experience_per_level' => 4000, 'drop_table' => ['gold_base' => 20000, 'gold_range' => 10000, 'item_chance' => 1.0]],
        ];

        foreach ($monsters as $monster) {
            GameMonsterDefinition::create(array_merge($monster, [
                'icon' => 'monster_'.strtolower(str_replace(' ', '_', $monster['name'])).'.png',
                'is_active' => true,
            ]));
        }
    }

    private function seedMapDefinitions(): void
    {
        $maps = [
            // 第一幕 - 森林 (1-10级)
            ['name' => '新手营地', 'act' => 1, 'min_level' => 1, 'max_level' => 3, 'monster_ids' => [1, 2], 'has_teleport' => true, 'teleport_cost' => 0, 'description' => '安全的训练场所'],
            ['name' => '幽暗森林', 'act' => 1, 'min_level' => 2, 'max_level' => 5, 'monster_ids' => [2, 3], 'has_teleport' => true, 'teleport_cost' => 10, 'description' => '野狼出没的森林'],
            ['name' => '哥布林巢穴', 'act' => 1, 'min_level' => 4, 'max_level' => 7, 'monster_ids' => [3, 4], 'has_teleport' => true, 'teleport_cost' => 20, 'description' => '哥布林的聚集地'],
            ['name' => '野猪平原', 'act' => 1, 'min_level' => 5, 'max_level' => 8, 'monster_ids' => [1, 6], 'has_teleport' => true, 'teleport_cost' => 35, 'description' => '野猪王领地'],
            ['name' => '树人圣地', 'act' => 1, 'min_level' => 6, 'max_level' => 10, 'monster_ids' => [4, 5], 'has_teleport' => true, 'teleport_cost' => 50, 'description' => '树人长老的领地'],

            // 第二幕 - 洞穴 (8-20级)
            ['name' => '黑暗洞穴入口', 'act' => 2, 'min_level' => 8, 'max_level' => 12, 'monster_ids' => [7, 8], 'has_teleport' => true, 'teleport_cost' => 100, 'description' => '通往地下的入口'],
            ['name' => '蜘蛛洞穴', 'act' => 2, 'min_level' => 10, 'max_level' => 14, 'monster_ids' => [8, 14], 'has_teleport' => true, 'teleport_cost' => 150, 'description' => '蜘蛛的巢穴'],
            ['name' => '骷髅墓地', 'act' => 2, 'min_level' => 12, 'max_level' => 16, 'monster_ids' => [9, 10], 'has_teleport' => true, 'teleport_cost' => 200, 'description' => '古老的墓地'],
            ['name' => '骸骨大厅', 'act' => 2, 'min_level' => 15, 'max_level' => 20, 'monster_ids' => [10, 11], 'has_teleport' => true, 'teleport_cost' => 300, 'description' => '骸骨之王的宫殿'],

            // 第三幕 - 地狱 (18-30级)
            ['name' => '地狱之门', 'act' => 3, 'min_level' => 18, 'max_level' => 22, 'monster_ids' => [12, 13], 'has_teleport' => true, 'teleport_cost' => 500, 'description' => '通往地狱的入口'],
            ['name' => '火焰平原', 'act' => 3, 'min_level' => 20, 'max_level' => 25, 'monster_ids' => [13, 16], 'has_teleport' => true, 'teleport_cost' => 700, 'description' => '燃烧的平原'],
            ['name' => '炎魔洞穴', 'act' => 3, 'min_level' => 22, 'max_level' => 26, 'monster_ids' => [13, 15], 'has_teleport' => true, 'teleport_cost' => 900, 'description' => '炎魔的栖息地'],
            ['name' => '恶魔要塞', 'act' => 3, 'min_level' => 23, 'max_level' => 28, 'monster_ids' => [15, 16], 'has_teleport' => true, 'teleport_cost' => 1000, 'description' => '恶魔的堡垒'],
            ['name' => '魔王宫殿', 'act' => 3, 'min_level' => 25, 'max_level' => 30, 'monster_ids' => [16, 14], 'has_teleport' => true, 'teleport_cost' => 1500, 'description' => '地狱魔王的宫殿'],

            // 第四幕 - 深渊 (28-40级)
            ['name' => '深渊入口', 'act' => 4, 'min_level' => 28, 'max_level' => 32, 'monster_ids' => [17, 18], 'has_teleport' => true, 'teleport_cost' => 2000, 'description' => '通往深渊的入口'],
            ['name' => '黑暗迷宫', 'act' => 4, 'min_level' => 30, 'max_level' => 35, 'monster_ids' => [18, 19], 'has_teleport' => true, 'teleport_cost' => 2500, 'description' => '充满危险的迷宫'],
            ['name' => '虚空裂隙', 'act' => 4, 'min_level' => 32, 'max_level' => 38, 'monster_ids' => [17, 19], 'has_teleport' => true, 'teleport_cost' => 3000, 'description' => '空间扭曲的裂隙'],
            ['name' => '深渊王座', 'act' => 4, 'min_level' => 35, 'max_level' => 40, 'monster_ids' => [19, 20], 'has_teleport' => true, 'teleport_cost' => 5000, 'description' => '深渊领主的王座'],

            // 第五幕 - 天界 (30-50级)
            ['name' => '天界入口', 'act' => 5, 'min_level' => 30, 'max_level' => 35, 'monster_ids' => [21, 22], 'has_teleport' => true, 'teleport_cost' => 4000, 'description' => '通往天界的阶梯'],
            ['name' => '天使圣殿', 'act' => 5, 'min_level' => 33, 'max_level' => 40, 'monster_ids' => [22, 23], 'has_teleport' => true, 'teleport_cost' => 5500, 'description' => '天使的圣殿'],
            ['name' => '荣耀大厅', 'act' => 5, 'min_level' => 38, 'max_level' => 45, 'monster_ids' => [24, 26], 'has_teleport' => true, 'teleport_cost' => 7000, 'description' => '荣耀的殿堂'],
            ['name' => '堕落天使领域', 'act' => 5, 'min_level' => 42, 'max_level' => 48, 'monster_ids' => [24, 25], 'has_teleport' => true, 'teleport_cost' => 8500, 'description' => '堕落天使的领地'],
            ['name' => '大天使圣殿', 'act' => 5, 'min_level' => 45, 'max_level' => 52, 'monster_ids' => [25, 26], 'has_teleport' => true, 'teleport_cost' => 10000, 'description' => '大天使长的圣殿'],

            // 第六幕 - 神域 (45-65级)
            ['name' => '神域入口', 'act' => 6, 'min_level' => 48, 'max_level' => 55, 'monster_ids' => [27, 28], 'has_teleport' => true, 'teleport_cost' => 12000, 'description' => '通往神域的入口'],
            ['name' => '神仆大厅', 'act' => 6, 'min_level' => 52, 'max_level' => 58, 'monster_ids' => [28, 29], 'has_teleport' => true, 'teleport_cost' => 15000, 'description' => '神仆的居所'],
            ['name' => '神官圣殿', 'act' => 6, 'min_level' => 55, 'max_level' => 62, 'monster_ids' => [29, 30], 'has_teleport' => true, 'teleport_cost' => 18000, 'description' => '神官的圣殿'],
            ['name' => '神将殿', 'act' => 6, 'min_level' => 58, 'max_level' => 65, 'monster_ids' => [30, 31], 'has_teleport' => true, 'teleport_cost' => 22000, 'description' => '神将的宫殿'],
            ['name' => '神王殿', 'act' => 6, 'min_level' => 60, 'max_level' => 70, 'monster_ids' => [31, 32], 'has_teleport' => true, 'teleport_cost' => 28000, 'description' => '神王化身的神殿'],
            ['name' => '审判之庭', 'act' => 6, 'min_level' => 62, 'max_level' => 68, 'monster_ids' => [31, 32], 'has_teleport' => true, 'teleport_cost' => 25000, 'description' => '审判天使的法庭'],

            // 第七幕 - 永恒之境 (60-80级)
            ['name' => '永恒入口', 'act' => 7, 'min_level' => 65, 'max_level' => 72, 'monster_ids' => [33, 34], 'has_teleport' => true, 'teleport_cost' => 35000, 'description' => '通往永恒的入口'],
            ['name' => '时空裂谷', 'act' => 7, 'min_level' => 68, 'max_level' => 75, 'monster_ids' => [34, 35], 'has_teleport' => true, 'teleport_cost' => 42000, 'description' => '时空扭曲的裂谷'],
            ['name' => '永恒战场', 'act' => 7, 'min_level' => 72, 'max_level' => 78, 'monster_ids' => [35, 36], 'has_teleport' => true, 'teleport_cost' => 50000, 'description' => '永恒的战场'],
            ['name' => '永恒法师塔', 'act' => 7, 'min_level' => 75, 'max_level' => 82, 'monster_ids' => [36, 37], 'has_teleport' => true, 'teleport_cost' => 58000, 'description' => '永恒法师的高塔'],
            ['name' => '永恒王座', 'act' => 7, 'min_level' => 78, 'max_level' => 85, 'monster_ids' => [37, 38], 'has_teleport' => true, 'teleport_cost' => 68000, 'description' => '永恒之王的王座'],
            ['name' => '永恒圣殿', 'act' => 7, 'min_level' => 80, 'max_level' => 88, 'monster_ids' => [38, 39], 'has_teleport' => true, 'teleport_cost' => 80000, 'description' => '永恒的圣殿'],

            // 第八幕 - 混沌虚空 (78-100级)
            ['name' => '混沌入口', 'act' => 8, 'min_level' => 82, 'max_level' => 88, 'monster_ids' => [40, 41], 'has_teleport' => true, 'teleport_cost' => 100000, 'description' => '通往混沌的入口'],
            ['name' => '虚空边缘', 'act' => 8, 'min_level' => 85, 'max_level' => 92, 'monster_ids' => [41, 42], 'has_teleport' => true, 'teleport_cost' => 120000, 'description' => '虚空的边缘'],
            ['name' => '混沌裂隙', 'act' => 8, 'min_level' => 88, 'max_level' => 95, 'monster_ids' => [42, 43], 'has_teleport' => true, 'teleport_cost' => 150000, 'description' => '混沌的裂隙'],
            ['name' => '混沌魔殿', 'act' => 8, 'min_level' => 92, 'max_level' => 98, 'monster_ids' => [43, 44], 'has_teleport' => true, 'teleport_cost' => 180000, 'description' => '混沌魔神的殿堂'],
            ['name' => '混沌源点', 'act' => 8, 'min_level' => 96, 'max_level' => 100, 'monster_ids' => [44, 45], 'has_teleport' => true, 'teleport_cost' => 220000, 'description' => '混沌的源头'],
            ['name' => '混沌王座', 'act' => 8, 'min_level' => 98, 'max_level' => 100, 'monster_ids' => [45, 46], 'has_teleport' => true, 'teleport_cost' => 300000, 'description' => '混沌之王的最终王座'],
        ];

        foreach ($maps as $index => $map) {
            GameMapDefinition::create(array_merge($map, [
                'background' => 'map_'.($index + 1).'.jpg',
                'is_active' => true,
            ]));
        }
    }
}
