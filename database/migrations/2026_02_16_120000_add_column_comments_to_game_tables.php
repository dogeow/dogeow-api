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
        // 添加字段注释
        $columnComments = [
            // game_characters
            'game_characters' => [
                // 'id' => '角色ID', // 跳过：被外键引用
                'user_id' => '所属用户ID',
                'name' => '角色名称',
                'class' => '职业：warrior战士/mage法师/ranger游侠',
                'level' => '等级',
                'experience' => '当前经验值',
                'copper' => '铜币',
                'strength' => '力量',
                'dexterity' => '敏捷',
                'vitality' => '体力',
                'energy' => '能量',
                'skill_points' => '可用技能点数',
                'stat_points' => '可用属性点数',
                'current_map_id' => '当前所在地图ID',
                'is_fighting' => '是否正在战斗中',
                'last_combat_at' => '最后战斗时间',
                'current_hp' => '当前生命值',
                'current_mana' => '当前法力值',
                'combat_monster_id' => '战斗中的怪物ID',
                'combat_monsters' => '战斗中的怪物列表（JSON）',
                'combat_monster_hp' => '战斗中怪物总血量',
                'combat_monster_max_hp' => '战斗中怪物总最大血量',
                'combat_rounds' => '战斗回合数',
                'combat_started_at' => '战斗开始时间',
                'combat_total_damage_dealt' => '战斗总伤害输出',
                'combat_total_damage_taken' => '战斗总受到伤害',
                'auto_use_hp_potion' => '自动使用生命药水',
                'hp_potion_threshold' => '生命药水使用阈值百分比',
                'auto_use_mp_potion' => '自动使用法力药水',
                'mp_potion_threshold' => '法力药水使用阈值百分比',
            ],

            // game_item_definitions
            'game_item_definitions' => [
                // 'id' => '物品定义ID', // 跳过：被外键引用
                'name' => '物品名称',
                'type' => '物品类型：weapon武器/helmet头盔/armor护甲/gloves手套/boots鞋子/belt腰带/ring戒指/amulet项链/potion药水',
                'sub_type' => '物品子类型：sword剑/axe斧/mace锤/staff法杖/bow弓/dagger匕首/cloth布甲/leather皮甲/mail锁甲/plate板甲',
                'base_stats' => '基础属性（JSON）',
                'required_level' => '需求等级',
                'required_strength' => '需求力量',
                'required_dexterity' => '需求敏捷',
                'required_energy' => '需求能量',
                'icon' => '图标',
                'description' => '描述',
                'is_active' => '是否启用',
            ],

            // game_items
            'game_items' => [
                // 'id' => '物品实例ID', // 跳过：被外键引用
                'character_id' => '所属角色ID',
                'definition_id' => '物品定义ID',
                'quality' => '品质：common普通/magic魔法/rare稀有/legendary传奇/mythic神话',
                'stats' => '物品属性（JSON）',
                'affixes' => '词缀（JSON）',
                'is_in_storage' => '是否在仓库中',
                'quantity' => '堆叠数量',
                'slot_index' => '背包格子索引',
            ],

            // game_equipment
            'game_equipment' => [
                // 'id' => '装备槽ID', // 跳过：可能有外键引用
                'character_id' => '所属角色ID',
                'slot' => '装备槽位：weapon武器/helmet头盔/armor护甲/gloves手套/boots鞋子/belt腰带/ring1戒指1/ring2戒指2/amulet项链',
                'item_id' => '装备的物品ID',
            ],

            // game_skill_definitions
            'game_skill_definitions' => [
                // 'id' => '技能定义ID', // 跳过：被外键引用
                'name' => '技能名称',
                'description' => '技能描述',
                'type' => '技能类型：active主动/passive被动',
                'class_restriction' => '职业限制：warrior战士/mage法师/ranger游侠/all全职业',
                'max_level' => '最大等级',
                'base_damage' => '基础伤害',
                'damage_per_level' => '每级伤害加成',
                'mana_cost' => '法力消耗',
                'mana_cost_per_level' => '每级法力消耗加成',
                'cooldown' => '冷却时间（秒）',
                'icon' => '图标',
                'effects' => '效果（JSON）',
                'skill_points_cost' => '学习消耗技能点数',
                'target_type' => '目标类型：single单体/all全体',
                'is_active' => '是否启用',
            ],

            // game_character_skills
            'game_character_skills' => [
                // 'id' => '角色技能ID', // 跳过：可能有外键引用
                'character_id' => '所属角色ID',
                'skill_id' => '技能定义ID',
                'level' => '技能等级',
                'slot_index' => '技能栏索引',
            ],

            // game_map_definitions
            'game_map_definitions' => [
                // 'id' => '地图ID', // 跳过：被外键引用
                'name' => '地图名称',
                'act' => '所属章节',
                'min_level' => '最低等级要求',
                'max_level' => '最高等级',
                'monster_ids' => '怪物ID列表（JSON）',
                'background' => '背景图',
                'description' => '地图描述',
                'is_active' => '是否启用',
            ],

            // game_character_maps
            'game_character_maps' => [
                // 'id' => '记录ID', // 跳过：可能有外键引用
                'character_id' => '所属角色ID',
                'map_id' => '地图ID',
                'unlocked' => '是否已解锁',
                'teleport_unlocked' => '传送点是否已解锁',
            ],

            // game_monster_definitions
            'game_monster_definitions' => [
                // 'id' => '怪物ID', // 跳过：被外键引用
                'name' => '怪物名称',
                'type' => '怪物类型：normal普通/elite精英/boss首领',
                'level' => '怪物等级',
                'hp_base' => '基础生命值',
                'hp_per_level' => '每级生命值加成',
                'attack_base' => '基础攻击力',
                'attack_per_level' => '每级攻击力加成',
                'defense_base' => '基础防御力',
                'defense_per_level' => '每级防御力加成',
                'experience_base' => '基础经验值',
                'experience_per_level' => '每级经验值加成',
                'drop_table' => '掉落表（JSON）',
                'icon' => '图标',
                'is_active' => '是否启用',
            ],

            // game_combat_logs
            'game_combat_logs' => [
                // 'id' => '日志ID', // 跳过：可能有外键引用
                'character_id' => '所属角色ID',
                'map_id' => '地图ID',
                'monster_id' => '怪物ID',
                'damage_dealt' => '造成的伤害',
                'damage_taken' => '受到的伤害',
                'victory' => '是否胜利',
                'loot_dropped' => '掉落物品（JSON）',
                'experience_gained' => '获得经验值',
                'copper_gained' => '获得铜币',
                'duration_seconds' => '战斗时长（秒）',
                'skills_used' => '使用的技能（JSON）',
            ],
        ];

        foreach ($columnComments as $table => $columns) {
            foreach ($columns as $column => $comment) {
                // 获取字段类型并添加注释
                $columnInfo = DB::selectOne(
                    "SELECT COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?",
                    [$table, $column]
                );
                if ($columnInfo) {
                    $type = $columnInfo->COLUMN_TYPE;
                    $nullClause = $columnInfo->IS_NULLABLE === 'YES' ? 'NULL' : 'NOT NULL';
                    $defaultClause = $columnInfo->COLUMN_DEFAULT !== null ? "DEFAULT '{$columnInfo->COLUMN_DEFAULT}'" : '';
                    $extra = $columnInfo->EXTRA;

                    // 跳过 auto_increment 的默认值设置
                    if (str_contains($extra, 'auto_increment')) {
                        DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` {$type} {$nullClause} AUTO_INCREMENT COMMENT '{$comment}'");
                    } else {
                        DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` {$type} {$nullClause} {$defaultClause} {$extra} COMMENT '{$comment}'");
                    }
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 注意：字段注释的回滚较为复杂，这里不处理
    }
};
