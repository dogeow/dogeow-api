<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * 若数据库中尚无药品定义，则插入商店/战斗用的药品（与 GameSeederData/items.php 中药品一致）。
     */
    public function up(): void
    {
        if (DB::table('game_item_definitions')->where('type', 'potion')->exists()) {
            return;
        }

        $potions = [
            [
                'name' => '轻型生命药水',
                'type' => 'potion',
                'sub_type' => 'hp',
                'base_stats' => json_encode(['max_hp' => 50]),
                'required_level' => 1,
                'required_strength' => 0,
                'required_dexterity' => 0,
                'required_energy' => 0,
                'sockets' => 0,
                'icon' => 'potion.png',
                'is_active' => true,
                'gem_stats' => json_encode(['restore' => 50]),
            ],
            [
                'name' => '生命药水',
                'type' => 'potion',
                'sub_type' => 'hp',
                'base_stats' => json_encode(['max_hp' => 100]),
                'required_level' => 5,
                'required_strength' => 0,
                'required_dexterity' => 0,
                'required_energy' => 0,
                'sockets' => 0,
                'icon' => 'potion.png',
                'is_active' => true,
                'gem_stats' => json_encode(['restore' => 100]),
            ],
            [
                'name' => '重型生命药水',
                'type' => 'potion',
                'sub_type' => 'hp',
                'base_stats' => json_encode(['max_hp' => 200]),
                'required_level' => 10,
                'required_strength' => 0,
                'required_dexterity' => 0,
                'required_energy' => 0,
                'sockets' => 0,
                'icon' => 'potion.png',
                'is_active' => true,
                'gem_stats' => json_encode(['restore' => 200]),
            ],
            [
                'name' => '超重型生命药水',
                'type' => 'potion',
                'sub_type' => 'hp',
                'base_stats' => json_encode(['max_hp' => 400]),
                'required_level' => 20,
                'required_strength' => 0,
                'required_dexterity' => 0,
                'required_energy' => 0,
                'sockets' => 0,
                'icon' => 'potion.png',
                'is_active' => true,
                'gem_stats' => json_encode(['restore' => 400]),
            ],
            [
                'name' => '轻型法力药水',
                'type' => 'potion',
                'sub_type' => 'mp',
                'base_stats' => json_encode(['max_mana' => 30]),
                'required_level' => 1,
                'required_strength' => 0,
                'required_dexterity' => 0,
                'required_energy' => 0,
                'sockets' => 0,
                'icon' => 'potion.png',
                'is_active' => true,
                'gem_stats' => json_encode(['restore' => 30]),
            ],
            [
                'name' => '法力药水',
                'type' => 'potion',
                'sub_type' => 'mp',
                'base_stats' => json_encode(['max_mana' => 60]),
                'required_level' => 5,
                'required_strength' => 0,
                'required_dexterity' => 0,
                'required_energy' => 0,
                'sockets' => 0,
                'icon' => 'potion.png',
                'is_active' => true,
                'gem_stats' => json_encode(['restore' => 60]),
            ],
            [
                'name' => '重型法力药水',
                'type' => 'potion',
                'sub_type' => 'mp',
                'base_stats' => json_encode(['max_mana' => 120]),
                'required_level' => 10,
                'required_strength' => 0,
                'required_dexterity' => 0,
                'required_energy' => 0,
                'sockets' => 0,
                'icon' => 'potion.png',
                'is_active' => true,
                'gem_stats' => json_encode(['restore' => 120]),
            ],
            [
                'name' => '超重型法力药水',
                'type' => 'potion',
                'sub_type' => 'mp',
                'base_stats' => json_encode(['max_mana' => 240]),
                'required_level' => 20,
                'required_strength' => 0,
                'required_dexterity' => 0,
                'required_energy' => 0,
                'sockets' => 0,
                'icon' => 'potion.png',
                'is_active' => true,
                'gem_stats' => json_encode(['restore' => 240]),
            ],
        ];

        foreach ($potions as $potion) {
            $potion['created_at'] = now();
            $potion['updated_at'] = now();
            DB::table('game_item_definitions')->insert($potion);
        }
    }

    public function down(): void
    {
        DB::table('game_item_definitions')->where('type', 'potion')->delete();
    }
};
