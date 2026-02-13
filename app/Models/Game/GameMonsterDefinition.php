<?php

namespace App\Models\Game;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GameMonsterDefinition extends Model
{
    protected $fillable = [
        'name',
        'type',
        'level',
        'hp_base',
        'hp_per_level',
        'attack_base',
        'attack_per_level',
        'defense_base',
        'defense_per_level',
        'experience_base',
        'experience_per_level',
        'drop_table',
        'icon',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'drop_table' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public const TYPES = ['normal', 'elite', 'boss'];

    public const TYPE_MULTIPLIERS = [
        'normal' => 0.1,
        'elite' => 0.25,
        'boss' => 0.5,
    ];

    /**
     * 获取战斗日志
     */
    public function combatLogs(): HasMany
    {
        return $this->hasMany(GameCombatLog::class, 'monster_id');
    }

    /**
     * 获取指定等级的生命值
     */
    public function getHpAtLevel(int $level): int
    {
        $baseMultiplier = self::TYPE_MULTIPLIERS[$this->type] ?? 1.0;

        return (int) (($this->hp_base + $this->hp_per_level * ($level - $this->level)) * $baseMultiplier);
    }

    /**
     * 获取指定等级的攻击力
     */
    public function getAttackAtLevel(int $level): int
    {
        $baseMultiplier = self::TYPE_MULTIPLIERS[$this->type] ?? 1.0;

        return (int) (($this->attack_base + $this->attack_per_level * ($level - $this->level)) * $baseMultiplier);
    }

    /**
     * 获取指定等级的防御力
     */
    public function getDefenseAtLevel(int $level): int
    {
        $baseMultiplier = self::TYPE_MULTIPLIERS[$this->type] ?? 1.0;

        return (int) (($this->defense_base + $this->defense_per_level * ($level - $this->level)) * $baseMultiplier);
    }

    /**
     * 获取指定等级的经验值
     */
    public function getExperienceAtLevel(int $level): int
    {
        $baseMultiplier = self::TYPE_MULTIPLIERS[$this->type] ?? 1.0;

        return (int) (($this->experience_base + $this->experience_per_level * ($level - $this->level)) * $baseMultiplier);
    }

    /**
     * 获取完整战斗属性
     */
    public function getCombatStats(int $level): array
    {
        return [
            'hp' => $this->getHpAtLevel($level),
            'attack' => $this->getAttackAtLevel($level),
            'defense' => $this->getDefenseAtLevel($level),
            'experience' => $this->getExperienceAtLevel($level),
        ];
    }

    /**
     * 生成掉落
     */
    public function generateLoot(int $characterLevel): array
    {
        $loot = [];
        $dropTable = $this->drop_table ?? [];

        // 基础金币掉落
        $goldBase = $dropTable['gold_base'] ?? ($this->level * 10);
        $goldRange = $dropTable['gold_range'] ?? ($this->level * 5);
        $loot['gold'] = rand($goldBase, $goldBase + $goldRange);

        $typeMultiplier = self::TYPE_MULTIPLIERS[$this->type] ?? 1.0;

        // 药水掉落概率（比装备高）
        $potionDropChance = ($dropTable['potion_chance'] ?? 0.15) * $typeMultiplier;
        if (rand(1, 100) <= $potionDropChance * 100) {
            // 随机选择药水类型
            $potionType = rand(1, 100) <= 60 ? 'hp' : 'mp';
            $loot['potion'] = [
                'type' => 'potion',
                'sub_type' => $potionType,
                'quality' => 'common',
                'level' => min($characterLevel, $this->level + 2),
            ];
        }

        // 装备掉落概率
        $dropChance = $dropTable['item_chance'] ?? 0.1;
        $actualDropChance = $dropChance * $typeMultiplier;

        if (rand(1, 100) <= $actualDropChance * 100) {
            // 随机选择物品类型
            $itemTypes = $dropTable['item_types'] ?? ['weapon', 'helmet', 'armor', 'gloves', 'boots'];
            $itemType = $itemTypes[array_rand($itemTypes)];

            // 随机品质
            $quality = $this->generateItemQuality($typeMultiplier);

            $loot['item'] = [
                'type' => $itemType,
                'quality' => $quality,
                'level' => min($characterLevel, $this->level + 3),
            ];
        }

        return $loot;
    }

    /**
     * 生成物品品质
     */
    private function generateItemQuality(float $typeMultiplier): string
    {
        $roll = rand(1, 100) * $typeMultiplier;

        return match (true) {
            $roll >= 99 => 'mythic',
            $roll >= 95 => 'legendary',
            $roll >= 85 => 'rare',
            $roll >= 60 => 'magic',
            default => 'common',
        };
    }
}
