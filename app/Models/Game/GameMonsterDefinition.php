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
        return $this->calculateStatAtLevel(
            $level,
            $this->hp_base,
            $this->hp_per_level
        );
    }

    /**
     * 获取指定等级的攻击力
     */
    public function getAttackAtLevel(int $level): int
    {
        return $this->calculateStatAtLevel(
            $level,
            $this->attack_base,
            $this->attack_per_level
        );
    }

    /**
     * 获取指定等级的防御力
     */
    public function getDefenseAtLevel(int $level): int
    {
        return $this->calculateStatAtLevel(
            $level,
            $this->defense_base,
            $this->defense_per_level
        );
    }

    /**
     * 获取指定等级的经验值
     */
    public function getExperienceAtLevel(int $level): int
    {
        return $this->calculateStatAtLevel(
            $level,
            $this->experience_base,
            $this->experience_per_level
        );
    }

    /**
     * 通用方法：获取指定等级的属性
     */
    private function calculateStatAtLevel(int $level, float $base, float $perLevel): int
    {
        $baseMultiplier = config('game.monster_type_multipliers')[$this->type] ?? 1.0;
        $computedLevel = max($level, $this->level); // 防止负数等级

        return (int) (($base + $perLevel * ($computedLevel - $this->level)) * $baseMultiplier);
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
     * @param int $characterLevel 角色等级
     * @return array 掉落物品
     */
    public function generateLoot(int $characterLevel): array
    {
        $loot = [];
        $dropTable = $this->drop_table ?? [];

        $typeMultiplier = config('game.monster_type_multipliers')[$this->type] ?? 1.0;

        // 铜币掉落：drop_table 的 copper_base / copper_range 直接表示铜币区间（如 8、7 表示 8～15 铜）
        $copperChance = $dropTable['copper_chance'] ?? 0.01;
        if ($this->rollChance($copperChance)) {
            $base = (int) ($dropTable['copper_base'] ?? max(1, $this->level));
            $range = (int) ($dropTable['copper_range'] ?? max(0, $this->level));
            $loot['copper'] = random_int($base, $base + $range);
        }

        // 药水掉落（暗黑2风格：简单直接）
        $potionDropChance = ($dropTable['potion_chance'] ?? 0.01) * $typeMultiplier;
        if ($this->rollChance($potionDropChance)) {
            // 简单药水系统：hp 或 mp
            $potionType = $this->weightedRandom(['hp' => 0.6, 'mp' => 0.4]);

            // 根据怪物等级决定药水等级
            $potionLevel = match (true) {
                $this->level <= 10 => 'minor',      // 轻型药水
                $this->level <= 30 => 'light',       // 药水
                $this->level <= 60 => 'medium',      // 强效药水
                default => 'full',                     // 超级药水
            };

            $loot['potion'] = [
                'type' => 'potion',
                'sub_type' => $potionType,
                'level' => $potionLevel,
            ];
        }

        // 装备掉落概率：使用 drop_table 配置的 item_chance，不再乘 type（避免 normal 仅 0.5%~1% 导致长期无掉落）
        $dropChance = $dropTable['item_chance'] ?? 0.001;
        if ($this->rollChance($dropChance)) {
            // 随机选择物品类型
            $itemTypes = $dropTable['item_types'] ?? ['weapon', 'helmet', 'armor', 'gloves', 'boots', 'ring', 'amulet', 'belt'];
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
     * 支持最低的0.001%的掉落概率
     */
    private function generateItemQuality(float $typeMultiplier): string
    {
        $roll = mt_rand(1, 100000) / 1000 * $typeMultiplier;
        $chances = config('game.item_quality_chances');

        // 从高到低依次判断
        $cumulative = 0;
        foreach ($chances as $quality => $chance) {
            $cumulative += $chance;
            if ($roll >= 100 - $cumulative) {
                return $quality;
            }
        }

        return 'common'; // 兜底品质
    }

    /**
     * 随机概率判断
     */
    private function rollChance(float $chance): bool
    {
        // $chance是0~1，例如0.12就是12%概率
        return mt_rand() / mt_getrandmax() < $chance;
    }

    /**
     * 加权随机选择
     */
    private function weightedRandom(array $weights): string
    {
        $sum = array_sum($weights);
        if ($sum <= 0) {
            return array_key_first($weights); // fallback
        }
        $rand = mt_rand() / mt_getrandmax() * $sum;
        $cumulative = 0;
        foreach ($weights as $key => $weight) {
            $cumulative += $weight;
            if ($rand <= $cumulative) {
                return $key;
            }
        }

        return array_key_last($weights); // fallback
    }
}
