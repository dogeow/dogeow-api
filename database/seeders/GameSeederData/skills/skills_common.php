<?php

// 通用职业技能，由 php artisan game:export-seeder-definitions 从数据库导出，供 GameSeeder 使用
return [
    [
        'name' => '治疗术',
        'type' => 'active',
        'class_restriction' => 'all',
        'mana_cost' => 20,
        'cooldown' => 8,
        'skill_points_cost' => 1,
        'effects' => [
            'heal' => true,
        ],
        'description' => '恢复生命值',
    ],
    [
        'name' => '力量强化',
        'type' => 'passive',
        'class_restriction' => 'all',
        'mana_cost' => 0,
        'cooldown' => 0,
        'skill_points_cost' => 1,
        'effects' => [
            'strength_bonus' => 2,
        ],
        'description' => '被动提升力量',
    ],
    [
        'name' => '敏捷强化',
        'type' => 'passive',
        'class_restriction' => 'all',
        'mana_cost' => 0,
        'cooldown' => 0,
        'skill_points_cost' => 1,
        'effects' => [
            'dexterity_bonus' => 2,
        ],
        'description' => '被动提升敏捷',
    ],
    [
        'name' => '体力强化',
        'type' => 'passive',
        'class_restriction' => 'all',
        'mana_cost' => 0,
        'cooldown' => 0,
        'skill_points_cost' => 1,
        'effects' => [
            'vitality_bonus' => 2,
        ],
        'description' => '被动提升体力',
    ],
    [
        'name' => '能量强化',
        'type' => 'passive',
        'class_restriction' => 'all',
        'mana_cost' => 0,
        'cooldown' => 0,
        'skill_points_cost' => 1,
        'effects' => [
            'energy_bonus' => 2,
        ],
        'description' => '被动提升能量',
    ],
    [
        'name' => '吸血',
        'type' => 'passive',
        'class_restriction' => 'all',
        'mana_cost' => 0,
        'cooldown' => 0,
        'skill_points_cost' => 2,
        'effects' => [
            'life_steal' => 0.05,
        ],
        'description' => '攻击时回复生命值',
    ],
    [
        'name' => '回蓝',
        'type' => 'passive',
        'class_restriction' => 'all',
        'mana_cost' => 0,
        'cooldown' => 0,
        'skill_points_cost' => 2,
        'effects' => [
            'mana_regen' => 2,
        ],
        'description' => '被动回复法力值',
    ],
];
