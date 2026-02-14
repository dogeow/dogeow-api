<?php

namespace App\Models\Game;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameCharacterMap extends Model
{
    protected $fillable = [
        'character_id',
        'map_id',
    ];

    /**
     * 获取所属角色
     */
    public function character(): BelongsTo
    {
        return $this->belongsTo(GameCharacter::class, 'character_id');
    }

    /**
     * 获取地图定义
     */
    public function map(): BelongsTo
    {
        return $this->belongsTo(GameMapDefinition::class, 'map_id');
    }
}
