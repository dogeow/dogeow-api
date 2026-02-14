<?php

namespace App\Http\Controllers\Api\Game;

use App\Events\Game\GameCombatUpdate;
use App\Events\Game\GameLootDropped;
use App\Http\Controllers\Controller;
use App\Http\Requests\StartCombatRequest;
use App\Models\Game\GameCharacter;
use App\Models\Game\GameCombatLog;
use App\Models\Game\GameItem;
use App\Models\Game\GameItemDefinition;
use App\Models\Game\GameMapDefinition;
use App\Models\Game\GameMonsterDefinition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CombatController extends Controller
{
    /**
     * 获取战斗状态
     */
    public function status(Request $request): JsonResponse
    {
        $character = $this->getCharacter($request);

        // 初始化HP/Mana（如果需要）
        $character->initializeHpMana();

        return $this->success([
            'is_fighting' => $character->is_fighting,
            'current_map' => $character->currentMap,
            'combat_stats' => $character->getCombatStats(),
            'current_hp' => $character->getCurrentHp(),
            'current_mana' => $character->getCurrentMana(),
            'last_combat_at' => $character->last_combat_at,
        ]);
    }

    /**
     * 开始挂机战斗
     */
    public function start(StartCombatRequest $request): JsonResponse
    {
        $character = $this->getCharacter($request);

        if (! $character->current_map_id) {
            return $this->error('请先选择一个地图');
        }

        if ($character->is_fighting) {
            return $this->error('已经在战斗中');
        }

        $character->is_fighting = true;
        $character->last_combat_at = now();
        $character->save();

        return $this->success([
            'is_fighting' => true,
            'message' => '开始自动战斗',
        ]);
    }

    /**
     * 停止挂机战斗
     */
    public function stop(Request $request): JsonResponse
    {
        $character = $this->getCharacter($request);

        $character->is_fighting = false;
        $character->save();

        return $this->success([
            'is_fighting' => false,
            'message' => '停止自动战斗',
        ]);
    }

    /**
     * 更新药水自动使用设置
     */
    public function updatePotionSettings(Request $request): JsonResponse
    {
        $request->validate([
            'auto_use_hp_potion' => 'nullable|boolean',
            'hp_potion_threshold' => 'nullable|integer|min:1|max:100',
            'auto_use_mp_potion' => 'nullable|boolean',
            'mp_potion_threshold' => 'nullable|integer|min:1|max:100',
        ]);

        $character = $this->getCharacter($request);

        if ($request->has('auto_use_hp_potion')) {
            $character->auto_use_hp_potion = $request->boolean('auto_use_hp_potion');
        }
        if ($request->has('hp_potion_threshold')) {
            $character->hp_potion_threshold = $request->integer('hp_potion_threshold');
        }
        if ($request->has('auto_use_mp_potion')) {
            $character->auto_use_mp_potion = $request->boolean('auto_use_mp_potion');
        }
        if ($request->has('mp_potion_threshold')) {
            $character->mp_potion_threshold = $request->integer('mp_potion_threshold');
        }

        $character->save();

        return $this->success([
            'character' => $character->toArray(),
        ], '药水设置已更新');
    }

    /**
     * 执行一次战斗（由前端定时调用或后端队列处理）
     */
    public function execute(Request $request): JsonResponse
    {
        $character = $this->getCharacter($request);

        if (! $character->is_fighting || ! $character->current_map_id) {
            return $this->error('当前不在战斗状态');
        }

        // 检查角色血量，如果血量不足则自动停止战斗
        $character->initializeHpMana();
        $currentHp = $character->getCurrentHp();

        if ($currentHp <= 0) {
            // 自动停止战斗
            $character->is_fighting = false;
            $character->save();

            return $this->error('角色血量不足，已自动停止战斗', [
                'auto_stopped' => true,
                'current_hp' => $currentHp,
            ]);
        }

        $map = $character->currentMap;
        if (! $map) {
            return $this->error('地图不存在');
        }

        // 获取怪物列表并随机选择一个
        $monsterIds = $map->monster_ids ?? [];
        if (empty($monsterIds)) {
            return $this->error('该地图没有怪物');
        }

        // 根据角色等级选择合适等级的怪物
        $monsterId = $monsterIds[array_rand($monsterIds)];
        $monster = GameMonsterDefinition::find($monsterId);

        if (! $monster) {
            return $this->error('怪物不存在');
        }

        // 执行战斗
        $result = $this->processCombat($character, $monster, $map);

        // 广播战斗更新
        broadcast(new GameCombatUpdate(
            $character->id,
            $result
        ));

        // 如果有掉落，广播掉落事件
        if (! empty($result['loot'])) {
            broadcast(new GameLootDropped(
                $character->id,
                $result['loot']
            ));
        }

        return $this->success($result);
    }

    /**
     * 获取战斗日志
     */
    public function logs(Request $request): JsonResponse
    {
        $character = $this->getCharacter($request);

        $logs = $character->combatLogs()
            ->with(['monster', 'map'])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return $this->success(['logs' => $logs]);
    }

    /**
     * 获取战斗统计
     */
    public function stats(Request $request): JsonResponse
    {
        $character = $this->getCharacter($request);

        $stats = [
            'total_battles' => $character->combatLogs()->count(),
            'total_victories' => $character->combatLogs()->where('victory', true)->count(),
            'total_defeats' => $character->combatLogs()->where('victory', false)->count(),
            'total_damage_dealt' => $character->combatLogs()->sum('damage_dealt'),
            'total_damage_taken' => $character->combatLogs()->sum('damage_taken'),
            'total_experience_gained' => $character->combatLogs()->sum('experience_gained'),
            'total_gold_gained' => $character->combatLogs()->sum('gold_gained'),
            'total_items_looted' => $character->combatLogs()
                ->whereNotNull('loot_dropped')
                ->count(),
        ];

        return $this->success(['stats' => $stats]);
    }

    /**
     * 处理战斗逻辑
     */
    private function processCombat(GameCharacter $character, GameMonsterDefinition $monster, GameMapDefinition $map): array
    {
        $startTime = now();

        // 初始化HP/Mana（如果是新角色）
        $character->initializeHpMana();

        // 获取角色当前和最大战斗属性
        $charStats = $character->getCombatStats();
        $charHp = $character->getCurrentHp();
        $charMaxHp = $charStats['max_hp'];
        $charAttack = $charStats['attack'];
        $charDefense = $charStats['defense'];
        $charCritRate = $charStats['crit_rate'];
        $charCritDamage = $charStats['crit_damage'];

        // 根据地图等级范围确定怪物等级
        $monsterLevel = rand(
            max($map->min_level, $monster->level - 3),
            min($map->max_level, $monster->level + 3)
        );
        $monsterStats = $monster->getCombatStats($monsterLevel);
        $monsterHp = $monsterStats['hp'];
        $monsterAttack = $monsterStats['attack'];
        $monsterDefense = $monsterStats['defense'];

        $totalDamageDealt = 0;
        $totalDamageTaken = 0;
        $rounds = 0;
        $maxRounds = 100;

        // 获取角色的主动技能（所有已学习的主动技能）
        $activeSkills = $character->skills()
            ->whereHas('skill', fn ($q) => $q->where('type', 'active'))
            ->with('skill')
            ->get();

        $skillCooldowns = [];
        $currentMana = $character->getCurrentMana();
        $charMaxMana = $charStats['max_mana'];

        // 战斗循环
        while ($charHp > 0 && $monsterHp > 0 && $rounds < $maxRounds) {
            $rounds++;

            // 角色攻击
            $isCrit = (rand(1, 100) / 100) <= $charCritRate;
            $baseDamage = max(1, $charAttack - $monsterDefense * 0.5);

            // 尝试使用技能
            $skillDamage = 0;
            $skillUsed = null;

            foreach ($activeSkills as $charSkill) {
                $skillId = $charSkill->skill_id;
                $cooldownEnd = $skillCooldowns[$skillId] ?? 0;
                $skill = $charSkill->skill;

                // 使用技能定义中的基础伤害和魔法消耗
                if ($currentMana >= $skill->mana_cost && $cooldownEnd <= $rounds) {
                    $skillDamage = $skill->damage;
                    $currentMana -= $skill->mana_cost;
                    $skillCooldowns[$skillId] = $rounds + (int) $skill->cooldown;
                    $skillUsed = $skill->name;
                    break;
                }
            }

            // 计算总伤害
            if ($skillDamage > 0) {
                $damage = (int) ($baseDamage + $skillDamage);
            } else {
                $damage = (int) ($baseDamage * ($isCrit ? $charCritDamage : 1));
            }

            $monsterHp -= $damage;
            $totalDamageDealt += $damage;

            // 怪物还击
            if ($monsterHp > 0) {
                $monsterDamage = (int) max(1, $monsterAttack - $charDefense * 0.3);
                $charHp -= $monsterDamage;
                $totalDamageTaken += $monsterDamage;
            }
        }

        // 战斗结果
        $victory = $charHp > 0 && $monsterHp <= 0;
        $loot = [];
        $experienceGained = 0;
        $goldGained = 0;

        if ($victory) {
            // 计算经验
            $experienceGained = $monsterStats['experience'];

            // 生成掉落
            $lootResult = $monster->generateLoot($character->level);
            $goldGained = $lootResult['gold'] ?? 0;

            // 添加经验和金币
            $levelUpResult = $character->addExperience($experienceGained);
            $character->gold += $goldGained;

            // 保存当前HP/Mana
            $character->current_hp = max(0, $charHp);
            $character->current_mana = max(0, $currentMana);
            $character->save();

            // 处理物品掉落
            if (isset($lootResult['item'])) {
                $item = $this->createItem($character, $lootResult['item']);
                if ($item) {
                    $loot['item'] = $item;
                } else {
                    // 背包满了，记录掉落丢失
                    $loot['item_lost'] = true;
                    $loot['item_lost_reason'] = '背包已满';
                }
            }

            // 处理药水掉落
            if (isset($lootResult['potion'])) {
                $potion = $this->createPotion($character, $lootResult['potion']);
                if ($potion) {
                    $loot['potion'] = $potion;
                }
            }

            // 处理宝石掉落（小概率）
            if (rand(1, 100) <= 15) { // 15% 掉落率
                $gem = $this->createGem($character, $character->level);
                if ($gem) {
                    $loot['gem'] = $gem;
                }
            }

            $loot['gold'] = $goldGained;

            // 自动使用药水
            $potionUsed = $this->tryAutoUsePotions($character, $charHp, $currentMana, $charStats);
            if (! empty($potionUsed)) {
                $loot['potion_used'] = $potionUsed;
                // 重新获取角色状态（可能已经更新）
                $charHp = $character->getCurrentHp();
                $currentMana = $character->getCurrentMana();
            }
        } else {
            // 战败惩罚：损失部分金币
            $goldLoss = (int) ($character->gold * 0.1);
            $character->gold -= $goldLoss;

            // 保存当前HP/Mana（战败时至少保留1点HP）
            $character->current_hp = max(1, $charHp);
            $character->current_mana = max(0, $currentMana);

            // 战败时自动停止战斗
            $character->is_fighting = false;
            $character->save();
        }

        // 记录战斗日志
        $combatLog = GameCombatLog::create([
            'character_id' => $character->id,
            'map_id' => $map->id,
            'monster_id' => $monster->id,
            'damage_dealt' => (int) $totalDamageDealt,
            'damage_taken' => (int) $totalDamageTaken,
            'victory' => $victory,
            'loot_dropped' => ! empty($loot) ? $loot : null,
            'experience_gained' => $experienceGained,
            'gold_gained' => $goldGained,
            'duration_seconds' => $startTime->diffInSeconds(now()),
        ]);

        return [
            'victory' => $victory,
            'defeat' => ! $victory,
            'auto_stopped' => ! $victory, // 战败时自动停止
            'monster' => [
                'name' => $monster->name,
                'type' => $monster->type,
                'level' => $monsterLevel,
            ],
            'damage_dealt' => (int) $totalDamageDealt,
            'damage_taken' => (int) $totalDamageTaken,
            'rounds' => $rounds,
            'experience_gained' => $experienceGained,
            'gold_gained' => $goldGained,
            'loot' => $loot,
            // 不返回模型对象，而是返回数组（包含 current_hp/current_mana）
            'character' => $character->toArray(),
            'combat_log_id' => $combatLog->id,
        ];
    }

    /**
     * 创建掉落物品
     */
    private function createItem(GameCharacter $character, array $itemData): ?GameItem
    {
        // 查找符合条件的物品定义
        $definition = GameItemDefinition::query()
            ->where('type', $itemData['type'])
            ->where('required_level', '<=', $itemData['level'])
            ->where('is_active', true)
            ->inRandomOrder()
            ->first();

        if (! $definition) {
            return null;
        }

        // 检查背包空间
        $inventoryCount = $character->items()->where('is_in_storage', false)->count();
        if ($inventoryCount >= InventoryController::INVENTORY_SIZE) {
            return null;
        }

        // 生成随机属性
        $stats = [];
        $quality = $itemData['quality'];
        $qualityMultiplier = GameItem::QUALITY_MULTIPLIERS[$quality];

        foreach ($definition->base_stats ?? [] as $stat => $value) {
            $stats[$stat] = (int) ($value * $qualityMultiplier * (0.8 + rand(0, 40) / 100));
        }

        // 生成词缀（魔法及以上品质）
        $affixes = [];
        $sockets = 0; // 宝石插槽
        if ($quality !== 'common') {
            $affixCount = match ($quality) {
                'magic' => rand(1, 2),
                'rare' => rand(2, 3),
                'legendary' => rand(3, 4),
                'mythic' => rand(4, 5),
                default => 0,
            };

            $possibleAffixes = [
                ['attack' => rand(5, 20)],
                ['defense' => rand(3, 15)],
                ['crit_rate' => rand(1, 5) / 100],
                ['crit_damage' => rand(10, 30) / 100],
                ['max_hp' => rand(20, 100)],
                ['max_mana' => rand(10, 50)],
            ];

            shuffle($possibleAffixes);
            $affixes = array_slice($possibleAffixes, 0, $affixCount);

            // 高品质装备获得宝石插槽（仅装备类型）
            if (in_array($definition->type, ['weapon', 'helmet', 'armor', 'gloves', 'boots', 'belt', 'ring', 'amulet'])) {
                $sockets = match ($quality) {
                    'magic' => rand(0, 1),
                    'rare' => rand(1, 2),
                    'legendary' => rand(2, 3),
                    'mythic' => rand(3, 4),
                    default => 0,
                };
            }
        }

        // 创建物品
        $item = GameItem::create([
            'character_id' => $character->id,
            'definition_id' => $definition->id,
            'quality' => $quality,
            'stats' => $stats,
            'affixes' => $affixes,
            'is_in_storage' => false,
            'quantity' => 1,
            'slot_index' => $this->findEmptySlot($character),
            'sockets' => $sockets,
        ]);

        return $item->load('definition');
    }

    /**
     * 创建掉落药水（暗黑2风格：简单分级）
     */
    private function createPotion(GameCharacter $character, array $potionData): ?GameItem
    {
        // 暗黑2风格药水系统
        $potionConfigs = [
            'hp' => [
                'minor' => ['name' => '轻型生命药水', 'restore' => 50],
                'light' => ['name' => '生命药水', 'restore' => 100],
                'medium' => ['name' => '重型生命药水', 'restore' => 200],
                'full' => ['name' => '超重型生命药水', 'restore' => 400],
            ],
            'mp' => [
                'minor' => ['name' => '轻型法力药水', 'restore' => 30],
                'light' => ['name' => '法力药水', 'restore' => 60],
                'medium' => ['name' => '重型法力药水', 'restore' => 120],
                'full' => ['name' => '超重型法力药水', 'restore' => 240],
            ],
        ];

        $type = $potionData['sub_type']; // hp 或 mp
        $level = $potionData['level']; // minor, light, medium, full

        if (! isset($potionConfigs[$type][$level])) {
            return null;
        }

        $config = $potionConfigs[$type][$level];

        // 检查背包中是否已有相同药水，可叠加
        $statKey = $type === 'hp' ? 'max_hp' : 'max_mana';

        $existingPotion = $character->items()
            ->whereHas('definition', function ($query) use ($type) {
                $query->where('type', 'potion')
                    ->where('sub_type', $type);
            })
            ->where('is_in_storage', false)
            ->first();

        if ($existingPotion) {
            $existingPotion->quantity += 1;
            $existingPotion->save();

            return $existingPotion->load('definition');
        }

        // 检查背包空间
        $inventoryCount = $character->items()->where('is_in_storage', false)->count();
        if ($inventoryCount >= InventoryController::INVENTORY_SIZE) {
            return null;
        }

        // 查找或创建药水定义
        $definition = GameItemDefinition::query()
            ->where('type', 'potion')
            ->where('sub_type', $type)
            ->whereJsonContains('gem_stats->restore', $config['restore'])
            ->first();

        if (! $definition) {
            $definition = GameItemDefinition::create([
                'name' => $config['name'],
                'type' => 'potion',
                'sub_type' => $type,
                'base_stats' => [$statKey => $config['restore']],
                'required_level' => 1,
                'required_strength' => 0,
                'required_dexterity' => 0,
                'required_energy' => 0,
                'icon' => 'potion',
                'description' => "恢复{$config['restore']}点".($type === 'hp' ? '生命值' : '法力值'),
                'is_active' => true,
                'sockets' => 0,
                'gem_stats' => ['restore' => $config['restore']],
            ]);
        }

        // 创建药水物品
        $potion = GameItem::create([
            'character_id' => $character->id,
            'definition_id' => $definition->id,
            'quality' => 'common',
            'stats' => $definition->base_stats ?? [],
            'affixes' => [],
            'is_in_storage' => false,
            'quantity' => 1,
            'slot_index' => $this->findEmptySlot($character),
            'sockets' => 0,
        ]);

        return $potion->load('definition');
    }

    /**
     * 创建掉落宝石
     */
    private function createGem(GameCharacter $character, int $level): ?GameItem
    {
        // 宝石属性类型
        $gemTypes = [
            ['attack' => rand(5, 15), 'name' => '攻击宝石'],
            ['defense' => rand(3, 10), 'name' => '防御宝石'],
            ['max_hp' => rand(20, 50), 'name' => '生命宝石'],
            ['max_mana' => rand(10, 30), 'name' => '法力宝石'],
            ['crit_rate' => rand(1, 3) / 100, 'name' => '暴击宝石'],
            ['crit_damage' => rand(5, 15) / 100, 'name' => '暴伤宝石'],
        ];

        $selectedGem = $gemTypes[array_rand($gemTypes)];
        $gemStats = $selectedGem;
        unset($gemStats['name']);

        // 检查背包空间
        $inventoryCount = $character->items()->where('is_in_storage', false)->count();
        if ($inventoryCount >= InventoryController::INVENTORY_SIZE) {
            return null;
        }

        // 创建宝石定义（临时创建，实际应用中应该有预设的宝石定义表）
        $definition = GameItemDefinition::create([
            'name' => $selectedGem['name'],
            'type' => 'gem',
            'sub_type' => null,
            'base_stats' => [],
            'required_level' => 1,
            'required_strength' => 0,
            'required_dexterity' => 0,
            'required_energy' => 0,
            'icon' => 'gem',
            'description' => '可镶嵌到装备上，提升属性',
            'is_active' => true,
            'sockets' => 0,
            'gem_stats' => $gemStats,
        ]);

        // 创建宝石物品
        $gem = GameItem::create([
            'character_id' => $character->id,
            'definition_id' => $definition->id,
            'quality' => 'common',
            'stats' => [],
            'affixes' => [],
            'is_in_storage' => false,
            'quantity' => 1,
            'slot_index' => $this->findEmptySlot($character),
            'sockets' => 0,
        ]);

        return $gem->load('definition');
    }

    /**
     * 尝试自动使用药水
     */
    private function tryAutoUsePotions(GameCharacter $character, int $currentHp, int $currentMana, array $charStats): array
    {
        $used = [];

        // 检查HP药水
        if ($character->auto_use_hp_potion) {
            $hpPercent = ($currentHp / $charStats['max_hp']) * 100;
            if ($hpPercent <= $character->hp_potion_threshold) {
                $potion = $this->findBestPotion($character, 'hp');
                if ($potion) {
                    $this->usePotionItem($character, $potion);
                    $used['hp'] = [
                        'name' => $potion->definition->name,
                        'restored' => $potion->definition->base_stats['max_hp'] ?? 0,
                    ];
                    // 更新当前HP
                    $currentHp = $character->getCurrentHp();
                }
            }
        }

        // 检查MP药水
        if ($character->auto_use_mp_potion) {
            $mpPercent = ($currentMana / $charStats['max_mana']) * 100;
            if ($mpPercent <= $character->mp_potion_threshold) {
                $potion = $this->findBestPotion($character, 'mp');
                if ($potion) {
                    $this->usePotionItem($character, $potion);
                    $used['mp'] = [
                        'name' => $potion->definition->name,
                        'restored' => $potion->definition->base_stats['max_mana'] ?? 0,
                    ];
                }
            }
        }

        return $used;
    }

    /**
     * 找到最好的药水
     */
    private function findBestPotion(GameCharacter $character, string $type): ?GameItem
    {
        return $character->items()
            ->where('is_in_storage', false)
            ->whereHas('definition', function ($query) use ($type) {
                $query->where('type', 'potion')
                    ->where('sub_type', $type);
            })
            ->with('definition')
            ->get()
            ->sortByDesc(fn ($item) => $item->definition->base_stats[$type === 'hp' ? 'max_hp' : 'max_mana'] ?? 0)
            ->first();
    }

    /**
     * 使用药水物品
     */
    private function usePotionItem(GameCharacter $character, GameItem $potion): void
    {
        $stats = $potion->definition->base_stats ?? [];
        $hpRestored = $stats['max_hp'] ?? 0;
        $manaRestored = $stats['max_mana'] ?? 0;

        if ($hpRestored > 0) {
            $character->restoreHp($hpRestored);
        }
        if ($manaRestored > 0) {
            $character->restoreMana($manaRestored);
        }

        // 消耗药水
        if ($potion->quantity > 1) {
            $potion->quantity--;
            $potion->save();
        } else {
            $potion->delete();
        }
    }

    /**
     * 查找空槽位
     */
    private function findEmptySlot(GameCharacter $character): ?int
    {
        $usedSlots = $character->items()
            ->where('is_in_storage', false)
            ->whereNotNull('slot_index')
            ->pluck('slot_index')
            ->toArray();

        for ($i = 0; $i < InventoryController::INVENTORY_SIZE; $i++) {
            if (! in_array($i, $usedSlots)) {
                return $i;
            }
        }

        return null;
    }

    /**
     * 使用药品
     */
    public function usePotion(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'item_id' => 'required|integer|exists:game_items,id',
        ]);

        $character = $this->getCharacter($request);

        // 查找药品
        $item = $character->items()
            ->where('id', $validated['item_id'])
            ->where('is_in_storage', false)
            ->with('definition')
            ->first();

        if (! $item) {
            return $this->error('物品不存在');
        }

        // 检查是否为药品
        if ($item->definition->type !== 'potion') {
            return $this->error('该物品不是药品');
        }

        // 检查数量
        if ($item->quantity < 1) {
            return $this->error('药品数量不足');
        }

        // 获取药品效果
        $stats = $item->definition->base_stats ?? [];
        $hpRestored = $stats['max_hp'] ?? 0;
        $manaRestored = $stats['max_mana'] ?? 0;

        // 恢复HP/Mana
        if ($hpRestored > 0) {
            $character->restoreHp($hpRestored);
        }
        if ($manaRestored > 0) {
            $character->restoreMana($manaRestored);
        }

        // 消耗药品
        if ($item->quantity > 1) {
            $item->quantity--;
            $item->save();
        } else {
            $item->delete();
        }

        return $this->success([
            'current_hp' => $character->getCurrentHp(),
            'current_mana' => $character->getCurrentMana(),
            'max_hp' => $character->getMaxHp(),
            'max_mana' => $character->getMaxMana(),
            'message' => $hpRestored > 0 && $manaRestored > 0
                ? "恢复了 {$hpRestored} 点生命值和 {$manaRestored} 点法力值"
                : ($hpRestored > 0 ? "恢复了 {$hpRestored} 点生命值" : "恢复了 {$manaRestored} 点法力值"),
        ], '药品使用成功');
    }

    /**
     * 获取角色
     */
    private function getCharacter(Request $request): GameCharacter
    {
        $characterId = $request->query('character_id') ?: $request->input('character_id');

        $query = GameCharacter::query()
            ->where('user_id', $request->user()->id);

        if ($characterId) {
            $query->where('id', $characterId);
        }

        return $query->firstOrFail();
    }
}
