<?php

namespace App\Models\Game;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameCombatLog extends Model
{
    protected $fillable = [
        'character_id',
        'map_id',
        'monster_id',
        'damage_dealt',
        'damage_taken',
        'victory',
        'loot_dropped',
        'experience_gained',
        'copper_gained',
        'duration_seconds',
        'skills_used',
        'potion_used',
    ];

    protected function casts(): array
    {
        return [
            'victory' => 'boolean',
            'loot_dropped' => 'array',
            'skills_used' => 'array',
            'potion_used' => 'array',
        ];
    }

    /**
     * 获取所属角色
     */
    public function character(): BelongsTo
    {
        return $this->belongsTo(GameCharacter::class, 'character_id');
    }

    /**
     * 获取地图
     */
    public function map(): BelongsTo
    {
        return $this->belongsTo(GameMapDefinition::class, 'map_id');
    }

    /**
     * 获取怪物
     */
    public function monster(): BelongsTo
    {
        return $this->belongsTo(GameMonsterDefinition::class, 'monster_id');
    }
}
