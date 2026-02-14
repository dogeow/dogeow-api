<?php

namespace App\Models\Game;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GameItem extends GameItemDefinition
{
    protected $fillable = [
        'character_id',
        'definition_id',
        'quality',
        'stats',
        'affixes',
        'is_in_storage',
        'quantity',
        'slot_index',
        'sockets',
    ];

    protected function casts(): array
    {
        return [
            'stats' => 'array',
            'affixes' => 'array',
            'is_in_storage' => 'boolean',
        ];
    }

    public const QUALITIES = [
        'common',
        'magic',
        'rare',
        'legendary',
        'mythic',
    ];

    public const QUALITY_COLORS = [
        'common' => '#ffffff',
        'magic' => '#6888ff',
        'rare' => '#ffcc00',
        'legendary' => '#ff8000',
        'mythic' => '#00ff00',
    ];

    public const QUALITY_MULTIPLIERS = [
        'common' => 1.0,
        'magic' => 1.3,
        'rare' => 1.6,
        'legendary' => 2.0,
        'mythic' => 2.5,
    ];

    /**
     * 获取所属角色
     */
    public function character(): BelongsTo
    {
        return $this->belongsTo(GameCharacter::class, 'character_id');
    }

    /**
     * 获取物品定义
     */
    public function definition(): BelongsTo
    {
        return $this->belongsTo(GameItemDefinition::class, 'definition_id');
    }

    /**
     * 获取装备上的宝石
     */
    public function gems(): HasMany
    {
        return $this->hasMany(GameItemGem::class, 'item_id')->orderBy('socket_index');
    }

    /**
     * 获取完整属性（基础 + 随机词缀 + 宝石）
     */
    public function getTotalStats(): array
    {
        $stats = $this->stats ?? [];

        // 添加随机词缀属性
        foreach ($this->affixes ?? [] as $affix) {
            foreach ($affix as $key => $value) {
                $stats[$key] = ($stats[$key] ?? 0) + $value;
            }
        }

        // 添加宝石属性
        foreach ($this->gems ?? [] as $gem) {
            $gemStats = $gem->getGemStats();
            foreach ($gemStats as $key => $value) {
                $stats[$key] = ($stats[$key] ?? 0) + $value;
            }
        }

        return $stats;
    }

    /**
     * 获取品质颜色
     */
    public function getQualityColor(): string
    {
        return self::QUALITY_COLORS[$this->quality] ?? '#ffffff';
    }

    /**
     * 获取品质倍率
     */
    public function getQualityMultiplier(): float
    {
        return self::QUALITY_MULTIPLIERS[$this->quality] ?? 1.0;
    }

    /**
     * 获取物品名称（带品质前缀）
     */
    public function getDisplayName(): string
    {
        $prefix = match ($this->quality) {
            'magic' => '魔法 ',
            'rare' => '稀有 ',
            'legendary' => '传奇 ',
            'mythic' => '神话 ',
            default => '',
        };

        return $prefix.($this->definition->name ?? '未知物品');
    }

    /**
     * 检查角色是否可以使用该物品
     */
    public function canEquip(GameCharacter $character): array
    {
        $definition = $this->definition;

        if ($character->level < $definition->required_level) {
            return [
                'can_equip' => false,
                'reason' => "需要等级 {$definition->required_level}",
            ];
        }

        if ($character->strength < $definition->required_strength) {
            return [
                'can_equip' => false,
                'reason' => "需要力量 {$definition->required_strength}",
            ];
        }

        if ($character->dexterity < $definition->required_dexterity) {
            return [
                'can_equip' => false,
                'reason' => "需要敏捷 {$definition->required_dexterity}",
            ];
        }

        if ($character->energy < $definition->required_energy) {
            return [
                'can_equip' => false,
                'reason' => "需要能量 {$definition->required_energy}",
            ];
        }

        return [
            'can_equip' => true,
            'reason' => null,
        ];
    }
}
