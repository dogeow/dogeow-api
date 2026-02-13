<?php

namespace App\Models\Game;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GameCharacter extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'class',
        'level',
        'experience',
        'gold',
        'strength',
        'dexterity',
        'vitality',
        'energy',
        'skill_points',
        'stat_points',
        'current_map_id',
        'is_fighting',
        'last_combat_at',
        'current_hp',
        'current_mana',
        'auto_use_hp_potion',
        'hp_potion_threshold',
        'auto_use_mp_potion',
        'mp_potion_threshold',
    ];

    protected function casts(): array
    {
        return [
            'is_fighting' => 'boolean',
            'last_combat_at' => 'datetime',
            'auto_use_hp_potion' => 'boolean',
            'auto_use_mp_potion' => 'boolean',
        ];
    }

    // 经验值升级表（每级所需总经验）
    public const EXPERIENCE_TABLE = [
        // 第1-10级 - 新手阶段
        1 => 0,
        2 => 100,
        3 => 250,
        4 => 500,
        5 => 1000,
        6 => 2000,
        7 => 4000,
        8 => 8000,
        9 => 16000,
        10 => 32000,
        // 第11-20级 - 初级阶段
        11 => 50000,
        12 => 75000,
        13 => 105000,
        14 => 140000,
        15 => 180000,
        16 => 225000,
        17 => 275000,
        18 => 330000,
        19 => 390000,
        20 => 455000,
        // 第21-30级 - 中级阶段
        21 => 525000,
        22 => 600000,
        23 => 680000,
        24 => 765000,
        25 => 855000,
        26 => 950000,
        27 => 1050000,
        28 => 1155000,
        29 => 1265000,
        30 => 1380000,
        // 第31-40级 - 高级阶段
        31 => 1500000,
        32 => 1625000,
        33 => 1755000,
        34 => 1890000,
        35 => 2030000,
        36 => 2175000,
        37 => 2325000,
        38 => 2480000,
        39 => 2640000,
        40 => 2805000,
        // 第41-50级 - 精英阶段
        41 => 2975000,
        42 => 3150000,
        43 => 3330000,
        44 => 3515000,
        45 => 3705000,
        46 => 3900000,
        47 => 4100000,
        48 => 4305000,
        49 => 4515000,
        50 => 4730000,
        // 第51-60级 - 大师阶段
        51 => 4950000,
        52 => 5175000,
        53 => 5405000,
        54 => 5640000,
        55 => 5880000,
        56 => 6125000,
        57 => 6375000,
        58 => 6630000,
        59 => 6890000,
        60 => 7155000,
        // 第61-70级 - 传奇阶段
        61 => 7425000,
        62 => 7700000,
        63 => 7980000,
        64 => 8265000,
        65 => 8555000,
        66 => 8850000,
        67 => 9150000,
        68 => 9455000,
        69 => 9765000,
        70 => 10080000,
        // 第71-80级 - 神话阶段
        71 => 10400000,
        72 => 10725000,
        73 => 11055000,
        74 => 11390000,
        75 => 11730000,
        76 => 12075000,
        77 => 12425000,
        78 => 12780000,
        79 => 13140000,
        80 => 13505000,
        // 第81-90级 - 半神阶段
        81 => 13875000,
        82 => 14250000,
        83 => 14628000,
        84 => 15012000,
        85 => 15402000,
        86 => 15798000,
        87 => 16200000,
        88 => 16608000,
        89 => 17022000,
        90 => 17442000,
        // 第91-100级 - 神阶段
        91 => 17868000,
        92 => 18300000,
        93 => 18738000,
        94 => 19182000,
        95 => 19632000,
        96 => 20088000,
        97 => 20550000,
        98 => 21018000,
        99 => 21492000,
        100 => 21972000,
    ];

    // 每级属性点奖励
    public const STAT_POINTS_PER_LEVEL = 5;

    public const SKILL_POINTS_PER_LEVEL = 1;

    // 职业基础属性
    public const CLASS_BASE_STATS = [
        'warrior' => [
            'strength' => 15,
            'dexterity' => 10,
            'vitality' => 15,
            'energy' => 5,
        ],
        'mage' => [
            'strength' => 5,
            'dexterity' => 10,
            'vitality' => 10,
            'energy' => 20,
        ],
        'ranger' => [
            'strength' => 10,
            'dexterity' => 20,
            'vitality' => 10,
            'energy' => 5,
        ],
    ];

    // 装备槽位
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
     * 获取所属用户
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 获取角色装备
     */
    public function equipment(): HasMany
    {
        return $this->hasMany(GameEquipment::class, 'character_id');
    }

    /**
     * 获取背包物品
     */
    public function items(): HasMany
    {
        return $this->hasMany(GameItem::class, 'character_id');
    }

    /**
     * 获取已学技能
     */
    public function skills(): HasMany
    {
        return $this->hasMany(GameCharacterSkill::class, 'character_id');
    }

    /**
     * 获取地图进度
     */
    public function mapProgress(): HasMany
    {
        return $this->hasMany(GameCharacterMap::class, 'character_id');
    }

    /**
     * 获取当前地图
     */
    public function currentMap(): BelongsTo
    {
        return $this->belongsTo(GameMapDefinition::class, 'current_map_id');
    }

    /**
     * 获取战斗日志
     */
    public function combatLogs(): HasMany
    {
        return $this->hasMany(GameCombatLog::class, 'character_id');
    }

    /**
     * 计算最大生命值
     */
    public function getMaxHp(): int
    {
        $baseHp = match ($this->class) {
            'warrior' => 100,
            'mage' => 60,
            'ranger' => 80,
            default => 80,
        };

        return (int) ($baseHp + $this->vitality * 5);
    }

    /**
     * 计算最大法力值
     */
    public function getMaxMana(): int
    {
        $baseMana = match ($this->class) {
            'warrior' => 30,
            'mage' => 100,
            'ranger' => 50,
            default => 50,
        };

        return (int) ($baseMana + $this->energy * 3);
    }

    /**
     * 计算攻击力
     */
    public function getAttack(): int
    {
        $baseAttack = match ($this->class) {
            'warrior' => $this->strength * 2,
            'mage' => $this->energy * 2,
            'ranger' => $this->dexterity * 2,
            default => $this->strength,
        };

        // 加上装备加成
        $equipmentBonus = $this->getEquipmentBonus('attack');

        return (int) ($baseAttack + $equipmentBonus);
    }

    /**
     * 计算防御力
     */
    public function getDefense(): int
    {
        $baseDefense = $this->vitality * 0.5 + $this->dexterity * 0.3;

        // 加上装备加成
        $equipmentBonus = $this->getEquipmentBonus('defense');

        return (int) ($baseDefense + $equipmentBonus);
    }

    /**
     * 计算暴击率
     */
    public function getCritRate(): float
    {
        $baseCrit = $this->dexterity * 0.1;

        // 加上装备加成
        $equipmentBonus = $this->getEquipmentBonus('crit_rate');

        return min(0.75, $baseCrit + $equipmentBonus);
    }

    /**
     * 计算暴击伤害
     */
    public function getCritDamage(): float
    {
        $baseCritDamage = 1.5; // 150%

        // 加上装备加成
        $equipmentBonus = $this->getEquipmentBonus('crit_damage');

        return $baseCritDamage + $equipmentBonus;
    }

    /**
     * 获取装备属性加成
     */
    public function getEquipmentBonus(string $stat): float
    {
        $bonus = 0;

        $equipmentSlots = $this->equipment()->with('item.definition', 'item')->get();

        foreach ($equipmentSlots as $slot) {
            if ($slot->item) {
                $itemStats = $slot->item->stats ?? [];
                $bonus += (float) ($itemStats[$stat] ?? 0);

                // 词缀加成
                $affixes = $slot->item->affixes ?? [];
                foreach ($affixes as $affix) {
                    if (isset($affix[$stat])) {
                        $bonus += (float) $affix[$stat];
                    }
                }
            }
        }

        return $bonus;
    }

    /**
     * 获取升级所需经验
     */
    public function getExperienceToNextLevel(): int
    {
        return self::EXPERIENCE_TABLE[$this->level + 1] ?? ($this->level * 5000);
    }

    /**
     * 获取当前等级总经验
     */
    public function getExperienceForCurrentLevel(): int
    {
        return self::EXPERIENCE_TABLE[$this->level] ?? 0;
    }

    /**
     * 添加经验值（自动升级）
     */
    public function addExperience(int $amount): array
    {
        $this->experience += $amount;
        $levelsGained = 0;

        while ($this->experience >= $this->getExperienceToNextLevel()) {
            $this->level++;
            $this->skill_points += self::SKILL_POINTS_PER_LEVEL;
            $this->stat_points += self::STAT_POINTS_PER_LEVEL;
            $levelsGained++;
        }

        $this->save();

        return [
            'experience_gained' => $amount,
            'levels_gained' => $levelsGained,
            'new_level' => $this->level,
            'total_experience' => $this->experience,
        ];
    }

    /**
     * 获取完整战斗属性
     */
    public function getCombatStats(): array
    {
        return [
            'max_hp' => $this->getMaxHp(),
            'max_mana' => $this->getMaxMana(),
            'attack' => $this->getAttack(),
            'defense' => $this->getDefense(),
            'crit_rate' => $this->getCritRate(),
            'crit_damage' => $this->getCritDamage(),
        ];
    }

    /**
     * 获取当前生命值（如果未设置则返回最大值）
     */
    public function getCurrentHp(): int
    {
        return $this->current_hp ?? $this->getMaxHp();
    }

    /**
     * 获取当前法力值（如果未设置则返回最大值）
     */
    public function getCurrentMana(): int
    {
        return $this->current_mana ?? $this->getMaxMana();
    }

    /**
     * 恢复生命值
     */
    public function restoreHp(int $amount): void
    {
        $maxHp = $this->getMaxHp();
        $currentHp = $this->getCurrentHp();
        $this->current_hp = min($maxHp, $currentHp + $amount);
        $this->save();
    }

    /**
     * 恢复法力值
     */
    public function restoreMana(int $amount): void
    {
        $maxMana = $this->getMaxMana();
        $currentMana = $this->getCurrentMana();
        $this->current_mana = min($maxMana, $currentMana + $amount);
        $this->save();
    }

    /**
     * 初始化HP/Mana（用于新角色或重置）
     * 只在字段为NULL时设置，不会覆盖已存在的值（包括0）
     */
    public function initializeHpMana(): void
    {
        $needsSave = false;

        // 只有当字段真正为NULL时才初始化（新角色）
        if ($this->current_hp === null && $this->getAttribute('current_hp') === null) {
            $this->current_hp = $this->getMaxHp();
            $needsSave = true;
        }

        if ($this->current_mana === null && $this->getAttribute('current_mana') === null) {
            $this->current_mana = $this->getMaxMana();
            $needsSave = true;
        }

        if ($needsSave) {
            $this->save();
        }
    }

    /**
     * 获取装备中的所有物品
     */
    public function getEquippedItems(): array
    {
        $equipped = [];
        $equipmentSlots = $this->equipment()->with('item.definition')->get();

        foreach ($equipmentSlots as $slot) {
            if ($slot->item) {
                $equipped[$slot->slot] = $slot->item;
            }
        }

        return $equipped;
    }
}
