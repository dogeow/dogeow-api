<?php

// 由 php artisan game:export-seeder-definitions 从数据库导出，供 GameSeeder 使用
return [
  0 =>
  [
    'id' => 138,
    'name' => '轻型生命药水',
    'type' => 'potion',
    'sub_type' => 'hp',
    'base_stats' => 
    [
      'max_hp' => 10,
      'price' => 5,
    ],
    'required_level' => 1,
    'required_strength' => 0,
    'required_dexterity' => 0,
    'required_energy' => 0,
    'sockets' => 0,
    'gem_stats' => 
    [
      'restore' => 50,
    ],
  ],
  1 =>
  [
    'id' => 139,
    'name' => '生命药水',
    'type' => 'potion',
    'sub_type' => 'hp',
    'base_stats' => 
    [
      'max_hp' => 50,
      'price' => 25,
    ],
    'required_level' => 5,
    'required_strength' => 0,
    'required_dexterity' => 0,
    'required_energy' => 0,
    'sockets' => 0,
    'gem_stats' => 
    [
      'restore' => 100,
    ],
  ],
  2 =>
  [
    'id' => 140,
    'name' => '强效生命药水',
    'type' => 'potion',
    'sub_type' => 'hp',
    'base_stats' => 
    [
      'max_hp' => 100,
      'price' => 50,
    ],
    'required_level' => 10,
    'required_strength' => 0,
    'required_dexterity' => 0,
    'required_energy' => 0,
    'sockets' => 0,
    'gem_stats' => 
    [
      'restore' => 200,
    ],
  ],
  3 =>
  [
    'id' => 141,
    'name' => '超级生命药水',
    'type' => 'potion',
    'sub_type' => 'hp',
    'base_stats' => 
    [
      'max_hp' => 200,
      'price' => 100,
    ],
    'required_level' => 20,
    'required_strength' => 0,
    'required_dexterity' => 0,
    'required_energy' => 0,
    'sockets' => 0,
    'gem_stats' => 
    [
      'restore' => 400,
    ],
  ],
  4 =>
  [
    'id' => 142,
    'name' => '轻型法力药水',
    'type' => 'potion',
    'sub_type' => 'mp',
    'base_stats' => 
    [
      'max_mana' => 10,
      'price' => 5,
    ],
    'required_level' => 1,
    'required_strength' => 0,
    'required_dexterity' => 0,
    'required_energy' => 0,
    'sockets' => 0,
    'gem_stats' => 
    [
      'restore' => 30,
    ],
  ],
  5 =>
  [
    'id' => 143,
    'name' => '法力药水',
    'type' => 'potion',
    'sub_type' => 'mp',
    'base_stats' => 
    [
      'max_mana' => 50,
      'price' => 25,
    ],
    'required_level' => 5,
    'required_strength' => 0,
    'required_dexterity' => 0,
    'required_energy' => 0,
    'sockets' => 0,
    'gem_stats' => 
    [
      'restore' => 60,
    ],
  ],
  6 =>
  [
    'id' => 144,
    'name' => '强效法力药水',
    'type' => 'potion',
    'sub_type' => 'mp',
    'base_stats' => 
    [
      'max_mana' => 100,
      'price' => 50,
    ],
    'required_level' => 10,
    'required_strength' => 0,
    'required_dexterity' => 0,
    'required_energy' => 0,
    'sockets' => 0,
    'gem_stats' => 
    [
      'restore' => 120,
    ],
  ],
  7 =>
  [
    'id' => 145,
    'name' => '超级法力药水',
    'type' => 'potion',
    'sub_type' => 'mp',
    'base_stats' => 
    [
      'max_mana' => 200,
      'price' => 100,
    ],
    'required_level' => 20,
    'required_strength' => 0,
    'required_dexterity' => 0,
    'required_energy' => 0,
    'sockets' => 0,
    'gem_stats' => 
    [
      'restore' => 240,
    ],
  ],
)
