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
        'damage',
        'mana_cost',
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
     * 检查职业是否可以使用该技能
     */
    public function canLearnByClass(string $class): bool
    {
        return $this->class_restriction === 'all' || $this->class_restriction === $class;
    }
}
