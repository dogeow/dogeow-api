<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 添加表注释
        $tableComments = [
            'game_characters' => '游戏角色表',
            'game_item_definitions' => '物品定义表（装备、药水等的模板）',
            'game_items' => '角色背包物品表',
            'game_equipment' => '角色装备表',
            'game_skill_definitions' => '技能定义表',
            'game_character_skills' => '角色已学技能表',
            'game_map_definitions' => '地图定义表',
            'game_character_maps' => '角色地图进度表',
            'game_monster_definitions' => '怪物定义表',
            'game_combat_logs' => '战斗日志表',
        ];

        foreach ($tableComments as $table => $comment) {
            DB::statement("ALTER TABLE `{$table}` COMMENT = '{$comment}'");
        }

        // 注意：字段注释的修改在单独的迁移文件中处理，因为需要跳过外键约束的字段
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 移除表注释
        $tables = [
            'game_characters',
            'game_item_definitions',
            'game_items',
            'game_equipment',
            'game_skill_definitions',
            'game_character_skills',
            'game_map_definitions',
            'game_character_maps',
            'game_monster_definitions',
            'game_combat_logs',
        ];

        foreach ($tables as $table) {
            DB::statement("ALTER TABLE `{$table}` COMMENT = ''");
        }

        // 注意：字段注释的回滚较为复杂，这里不处理
    }
};
