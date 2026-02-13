<?php

namespace App\Models\Game;

use Illuminate\Database\Eloquent\Model;

class GameSkillDefinition extends Model
{
    protected $fillable = [
        'name',
        'description',
        'type',
        'class_restriction',
        'max_level',
        'base_damage',
        'damage_per_level',
        'mana_cost',
        'mana_cost_per_level',
        'cooldown',
        'icon',
        'effects',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'effects' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public const TYPES = ['active', 'passive'];

    public const CLASS_RESTRICTIONS = ['warrior', 'mage', 'ranger', 'all'];

    /**
     * 获取技能在指定等级的伤害
     */
    public function getDamageAtLevel(int $level): float
    {
        return $this->base_damage + ($this->damage_per_level * ($level - 1));
    }

    /**
     * 获取技能在指定等级的法力消耗
     */
    public function getManaCostAtLevel(int $level): int
    {
        return (int) ($this->mana_cost + ($this->mana_cost_per_level * ($level - 1)));
    }

    /**
     * 检查职业是否可以使用该技能
     */
    public function canLearnByClass(string $class): bool
    {
        return $this->class_restriction === 'all' || $this->class_restriction === $class;
    }
}
