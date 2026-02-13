<?php

namespace App\Models\Game;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameEquipment extends Model
{
    protected $fillable = [
        'character_id',
        'slot',
        'item_id',
    ];

    public const SLOTS = [
        'weapon',
        'helmet',
        'armor',
        'gloves',
        'boots',
        'belt',
        'ring1',
        'ring2',
        'amulet',
    ];

    /**
     * 获取所属角色
     */
    public function character(): BelongsTo
    {
        return $this->belongsTo(GameCharacter::class, 'character_id');
    }

    /**
     * 获取装备的物品
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(GameItem::class, 'item_id');
    }
}
