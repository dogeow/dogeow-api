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
use Throwable;

class CombatController extends Controller
{
    /**
     * 获取战斗状态
     */
    public function status(Request $request): JsonResponse
    {
        try {
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
        } catch (Throwable $e) {
            return $this->error('获取战斗状态失败', ['error' => $e->getMessage()]);
        }
    }

    /**
     * 开始挂机战斗
     */
    public function start(StartCombatRequest $request): JsonResponse
    {
        try {
            $character = $this->getCharacter($request);

            if (empty($character->current_map_id)) {
                return $this->error('请先选择一个地图');
            }

            if ($character->is_fighting) {
                return $this->error('已经在战斗中');
            }

            $character->fill([
                'is_fighting' => true,
                'last_combat_at' => now(),
            ])->save();

            return $this->success([
                'is_fighting' => true,
                'message' => '开始自动战斗',
            ]);
        } catch (Throwable $e) {
            return $this->error('启动战斗失败', ['error' => $e->getMessage()]);
        }
    }

    /**
     * 停止挂机战斗
     */
    public function stop(Request $request): JsonResponse
    {
        try {
            $character = $this->getCharacter($request);

            $character->clearCombatState();
            $character->update(['is_fighting' => false]);

            return $this->success([
                'is_fighting' => false,
                'message' => '停止自动战斗',
            ]);
        } catch (Throwable $e) {
            return $this->error('停止战斗失败', ['error' => $e->getMessage()]);
        }
    }

    /**
     * 更新药水自动使用设置
     */
    public function updatePotionSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'auto_use_hp_potion' => 'nullable|boolean',
            'hp_potion_threshold' => 'nullable|integer|min:1|max:100',
            'auto_use_mp_potion' => 'nullable|boolean',
            'mp_potion_threshold' => 'nullable|integer|min:1|max:100',
        ]);
        try {
            $character = $this->getCharacter($request);

            foreach (['auto_use_hp_potion', 'hp_potion_threshold', 'auto_use_mp_potion', 'mp_potion_threshold'] as $key) {
                if (array_key_exists($key, $validated)) {
                    $character->$key = $validated[$key];
                }
            }
            $character->save();

            return $this->success([
                'character' => $character->toArray(),
            ], '药水设置已更新');
        } catch (Throwable $e) {
            return $this->error('更新药水自动使用设置失败', ['error' => $e->getMessage()]);
        }
    }

    /**
     * 执行一回合战斗（每次请求只打一回合，怪物需多次请求才死）
     */
    public function execute(Request $request): JsonResponse
    {
        try {
            $character = $this->getCharacter($request);

            if (!$character->is_fighting || !$character->current_map_id) {
                return $this->error('当前不在战斗状态');
            }

            $character->initializeHpMana();
            $currentHp = $character->getCurrentHp();

            if ($currentHp <= 0) {
                $character->clearCombatState();
                $character->update(['is_fighting' => false]);
                return $this->error('角色血量不足，已自动停止战斗', [
                    'auto_stopped' => true,
                    'current_hp' => $currentHp,
                ]);
            }

            $map = $character->currentMap;
            if (!$map) {
                return $this->error('地图不存在');
            }

            // 把怪物获取与初始化逻辑提取到单独方法
            [
                $monster,
                $monsterLevel,
                $monsterStats,
                $monsterHp,
                $monsterMaxHp
            ] = $this->prepareMonsterInfo($character, $map);

            if (!$monster) {
                return $this->error('当前战斗怪物不存在，已清除状态');
            }
            if ($monster === 'no-monster') {
                // 地图没有怪，停止
                $character->update(['is_fighting' => false]);
                return $this->error('该地图没有怪物，已自动停止战斗，请选择其他地图', [
                    'auto_stopped' => true,
                ]);
            }

            [
                'roundResult' => $roundResult,
                'currentRound' => $currentRound,
                'requestedSkillIds' => $requestedSkillIds
            ] = $this->prepareRoundAndProcess(
                $request,
                $character,
                $monster,
                $monsterLevel,
                $monsterStats,
                $monsterHp,
                $monsterMaxHp
            );

            // 统一存储逻辑
            $this->persistCombatState($character, $roundResult, $currentRound);

            // 战胜逻辑
            if ($roundResult['victory']) {
                return $this->handleVictory(
                    $character,
                    $map,
                    $monster,
                    $monsterLevel,
                    $monsterMaxHp,
                    $currentRound,
                    $monsterStats,
                    $roundResult,
                    $monsterHp
                );
            }

            // 失败逻辑
            if ($roundResult['defeat']) {
                return $this->handleDefeat(
                    $character,
                    $map,
                    $monster,
                    $monsterLevel,
                    $monsterMaxHp,
                    $currentRound,
                    $roundResult,
                    $monsterHp
                );
            }

            // 普通回合（怪未死，角色未死）
            $character->combat_monster_hp = max(0, $roundResult['new_monster_hp']);
            $character->save();
            $result = [
                'victory' => false,
                'defeat' => false,
                'monster' => [
                    'name' => $monster->name,
                    'type' => $monster->type,
                    'level' => $monsterLevel,
                    'hp' => max(0, $roundResult['new_monster_hp']),
                    'max_hp' => $monsterMaxHp,
                ],
                'monster_hp_before_round' => $monsterHp,
                'damage_dealt' => $roundResult['round_damage_dealt'],
                'damage_taken' => $roundResult['round_damage_taken'],
                'rounds' => $currentRound,
                'experience_gained' => 0,
                'copper_gained' => 0,
                'loot' => [],
                'skills_used' => $roundResult['skills_used_this_round'],
                'character' => $character->fresh()->toArray(),
            ];
            broadcast(new GameCombatUpdate($character->id, $result));

            return $this->success($result);

        } catch (Throwable $e) {
            return $this->error('战斗执行失败', ['error' => $e->getMessage()]);
        }
    }

    /**
     * 提取：怪物获取与初始化相关
     */
    private function prepareMonsterInfo(GameCharacter $character, GameMapDefinition $map)
    {
        // 复用变量约定
        $monster = null;
        $monsterLevel = null;
        $monsterStats = null;
        $monsterHp = null;
        $monsterMaxHp = null;

        if ($character->hasActiveCombat()) {
            $monster = GameMonsterDefinition::query()->find($character->combat_monster_id);
            if (!$monster) {
                $character->clearCombatState();
                return [null, null, null, null, null];
            }
            $monsterLevel = (int) $character->combat_monster_level;
            $monsterStats = $monster->getCombatStats($monsterLevel);
            $monsterHp = (int) $character->combat_monster_hp;
            $monsterMaxHp = (int) $character->combat_monster_max_hp;
            return [$monster, $monsterLevel, $monsterStats, $monsterHp, $monsterMaxHp];
        } else {
            $monsters = $map->getMonsters();
            if (empty($monsters)) {
                return ['no-monster', null, null, null, null];
            }
            $monster = $monsters[array_rand($monsters)];
            $monsterLevel = rand(
                max($map->min_level, $monster->level - 3),
                min($map->max_level, $monster->level + 3)
            );
            $monsterStats = $monster->getCombatStats($monsterLevel);
            $difficulty = $character->getDifficultyMultipliers();
            $monsterMaxHp = (int) ($monsterStats['hp'] * $difficulty['monster_hp']);
            $monsterHp = $monsterMaxHp;

            // 角色 combat 状态初始化
            $character->combat_monster_id = $monster->id;
            $character->combat_monster_level = $monsterLevel;
            $character->combat_monster_hp = $monsterHp;
            $character->combat_monster_max_hp = $monsterMaxHp;
            $character->combat_total_damage_dealt = 0;
            $character->combat_total_damage_taken = 0;
            $character->combat_rounds = 0;
            $character->combat_skills_used = null;
            $character->combat_skill_cooldowns = null;
            $character->combat_started_at = now();

            return [$monster, $monsterLevel, $monsterStats, $monsterHp, $monsterMaxHp];
        }
    }

    /**
     * 提取：回合数据准备与处理
     */
    private function prepareRoundAndProcess(
        Request $request,
        GameCharacter $character,
        GameMonsterDefinition $monster,
        $monsterLevel,
        $monsterStats,
        $monsterHp,
        $monsterMaxHp
    ) {
        $currentRound = (int) $character->combat_rounds + 1;
        $skillCooldowns = $character->combat_skill_cooldowns ?? [];
        $skillsUsedAggregated = $character->combat_skills_used ?? [];
$requestedSkillIds = $request->input('skill_ids');
            if (!is_array($requestedSkillIds)) {
                $single = $request->input('skill_id');
                $requestedSkillIds = $single !== null ? [(int) $single] : [];
            } else {
                $requestedSkillIds = array_map('intval', array_values($requestedSkillIds));
            }

            $roundResult = $this->processOneRound(
                $character,
                $monster,
                $monsterLevel,
                $monsterStats,
                $monsterHp,
                $monsterMaxHp,
                $currentRound,
                $skillCooldowns,
                $skillsUsedAggregated,
                $requestedSkillIds
            );

        return [
            'roundResult' => $roundResult,
            'currentRound' => $currentRound,
            'requestedSkillIds' => $requestedSkillIds,
        ];
    }

    /**
     * 提取：统一本轮伤害、法力等持久化赋值
     */
    private function persistCombatState(GameCharacter $character, array $roundResult, int $currentRound)
    {
        $character->current_hp = max(0, $roundResult['new_char_hp']);
        $character->current_mana = max(0, $roundResult['new_char_mana']);
        $character->combat_total_damage_dealt += $roundResult['round_damage_dealt'];
        $character->combat_total_damage_taken += $roundResult['round_damage_taken'];
        $character->combat_rounds = $currentRound;
        $character->combat_skills_used = $roundResult['new_skills_aggregated'];
        $character->combat_skill_cooldowns = $roundResult['new_cooldowns'];
    }

    /**
     * 提取：胜利胜出后的业务处理
     */
    private function handleVictory(
        GameCharacter $character,
        GameMapDefinition $map,
        GameMonsterDefinition $monster,
        int $monsterLevel,
        int $monsterMaxHp,
        int $currentRound,
        array $monsterStats,
        array $roundResult,
        int $monsterHpBeforeRound
    ): JsonResponse {
        $difficulty = $character->getDifficultyMultipliers();
        $experienceGained = (int) ($monsterStats['experience'] * $difficulty['reward']);
        $lootResult = $monster->generateLoot($character->level);
        $copperGained = (int) (($lootResult['copper'] ?? 0) * $difficulty['reward']);
        $character->addExperience($experienceGained);
        $character->copper += $copperGained;

        $loot = [];
        if (isset($lootResult['item'])) {
            $item = $this->createItem($character, $lootResult['item']);
            $item ? $loot['item'] = $item : $loot += ['item_lost' => true, 'item_lost_reason' => '背包已满'];
        }
        if (isset($lootResult['potion'])) {
            $potion = $this->createPotion($character, $lootResult['potion']);
            if ($potion) {
                $loot['potion'] = $potion;
            }
        }
        if (rand(1, 200) <= 1) {
            $gem = $this->createGem($character, $character->level);
            if ($gem) {
                $loot['gem'] = $gem;
            }
        }
        $loot['copper'] = $copperGained;

        $charStats = $character->getCombatStats();
        $potionUsed = $this->tryAutoUsePotions($character, $roundResult['new_char_hp'], $roundResult['new_char_mana'], $charStats);
        if (!empty($potionUsed)) {
            $loot['potion_used'] = $potionUsed;
        }

        $startTime = $character->combat_started_at ?? now();
        $combatLog = GameCombatLog::create([
            'character_id' => $character->id,
            'map_id' => $map->id,
            'monster_id' => $monster->id,
            'damage_dealt' => $character->combat_total_damage_dealt,
            'damage_taken' => $character->combat_total_damage_taken,
            'victory' => true,
            'loot_dropped' => !empty($loot) ? $loot : null,
            'experience_gained' => $experienceGained,
            'copper_gained' => $copperGained,
            'duration_seconds' => $startTime->diffInSeconds(now()),
            'skills_used' => $roundResult['new_skills_aggregated'],
        ]);

        $character->clearCombatState();
        $character->save();

        $result = [
            'victory' => true,
            'defeat' => false,
            'auto_stopped' => false,
            'monster' => [
                'name' => $monster->name,
                'type' => $monster->type,
                'level' => $monsterLevel,
                'hp' => 0,
                'max_hp' => $monsterMaxHp,
            ],
            'monster_hp_before_round' => $monsterHpBeforeRound,
            'damage_dealt' => $character->combat_total_damage_dealt,
            'damage_taken' => $character->combat_total_damage_taken,
            'rounds' => $currentRound,
            'experience_gained' => $experienceGained,
            'copper_gained' => $copperGained,
            'loot' => $loot,
            'skills_used' => $roundResult['new_skills_aggregated'],
            'character' => $character->fresh()->toArray(),
            'combat_log_id' => $combatLog->id,
        ];
        broadcast(new GameCombatUpdate($character->id, $result));
        if (!empty($loot)) {
            broadcast(new GameLootDropped($character->id, $loot));
        }

        return $this->success($result);
    }

    /**
     * 提取：失败后的业务处理
     */
    private function handleDefeat(
        GameCharacter $character,
        GameMapDefinition $map,
        GameMonsterDefinition $monster,
        int $monsterLevel,
        int $monsterMaxHp,
        int $currentRound,
        array $roundResult,
        int $monsterHpBeforeRound
    ): JsonResponse {
        $copperLoss = (int) ($character->copper * 0.1);
        $character->copper -= $copperLoss;
        $character->current_hp = max(1, $roundResult['new_char_hp']);
        $character->is_fighting = false;

        $startTime = $character->combat_started_at ?? now();
        $combatLog = GameCombatLog::create([
            'character_id' => $character->id,
            'map_id' => $map->id,
            'monster_id' => $monster->id,
            'damage_dealt' => $character->combat_total_damage_dealt,
            'damage_taken' => $character->combat_total_damage_taken,
            'victory' => false,
            'loot_dropped' => null,
            'experience_gained' => 0,
            'copper_gained' => 0,
            'duration_seconds' => $startTime->diffInSeconds(now()),
            'skills_used' => $roundResult['new_skills_aggregated'],
        ]);

        $character->clearCombatState();
        $character->save();

        $result = [
            'victory' => false,
            'defeat' => true,
            'auto_stopped' => true,
            'monster' => [
                'name' => $monster->name,
                'type' => $monster->type,
                'level' => $monsterLevel,
                'hp' => max(0, $roundResult['new_monster_hp']),
                'max_hp' => $monsterMaxHp,
            ],
            'monster_hp_before_round' => $monsterHpBeforeRound,
            'damage_dealt' => $character->combat_total_damage_dealt,
            'damage_taken' => $character->combat_total_damage_taken,
            'rounds' => $currentRound,
            'experience_gained' => 0,
            'copper_gained' => 0,
            'loot' => [],
            'skills_used' => $roundResult['new_skills_aggregated'],
            'character' => $character->fresh()->toArray(),
            'combat_log_id' => $combatLog->id,
        ];
        broadcast(new GameCombatUpdate($character->id, $result));

        return $this->success($result);
    }

    /**
     * 获取战斗日志
     */
    public function logs(Request $request): JsonResponse
    {
        try {
            $character = $this->getCharacter($request);

            $logs = $character->combatLogs()
                ->with(['monster', 'map'])
                ->orderByDesc('created_at')
                ->limit(50)
                ->get();

            return $this->success(['logs' => $logs]);
        } catch (Throwable $e) {
            return $this->error('获取战斗日志失败', ['error' => $e->getMessage()]);
        }
    }

    /**
     * 获取战斗统计
     */
    public function stats(Request $request): JsonResponse
    {
        try {
            $character = $this->getCharacter($request);
            $combatLogs = $character->combatLogs();

            $stats = [
                'total_battles' => $combatLogs->count(),
                'total_victories' => (clone $combatLogs)->where('victory', true)->count(),
                'total_defeats' => (clone $combatLogs)->where('victory', false)->count(),
                'total_damage_dealt' => $combatLogs->sum('damage_dealt'),
                'total_damage_taken' => $combatLogs->sum('damage_taken'),
                'total_experience_gained' => $combatLogs->sum('experience_gained'),
                'total_copper_gained' => $combatLogs->sum('copper_gained'),
                'total_items_looted' => (clone $combatLogs)->whereNotNull('loot_dropped')->count(),
            ];

            return $this->success(['stats' => $stats]);
        } catch (Throwable $e) {
            return $this->error('获取战斗统计失败', ['error' => $e->getMessage()]);
        }
    }

    /**
     * 处理一回合战斗：角色攻击一次，怪物若未死则还击一次。
     * 仅当传入 requestedSkillIds 且角色拥有该技能、法力与冷却满足时才会施放技能（按数组顺序取第一个可用的），否则普通攻击。
     *
     * @param  array<int>  $requestedSkillIds 本回合允许施放的技能 id 列表，按顺序尝试，用第一个可用的
     * @param  array<int, array{skill_id: int, name: string, icon: string|null, use_count: int}>  $skillsUsedAggregated
     * @return array{round_damage_dealt: int, round_damage_taken: int, new_monster_hp: int, new_char_hp: int, new_char_mana: int, victory: bool, defeat: bool, skills_used_this_round: array, new_cooldowns: array, new_skills_aggregated: array}
     */
    private function processOneRound(
        GameCharacter $character,
        GameMonsterDefinition $monster,
        int $monsterLevel,
        array $monsterStats,
        int $monsterHp,
        int $monsterMaxHp,
        int $currentRound,
        array $skillCooldowns,
        array $skillsUsedAggregated,
        array $requestedSkillIds = []
    ): array {
        $character->initializeHpMana();

        $charStats = $character->getCombatStats();
        $charHp = $character->getCurrentHp();
        $currentMana = $character->getCurrentMana();
        $charAttack = $charStats['attack'];
        $charDefense = $charStats['defense'];
        $charCritRate = $charStats['crit_rate'];
        $charCritDamage = $charStats['crit_damage'];

        $difficulty = $character->getDifficultyMultipliers();
        $monsterAttack = (int) ($monsterStats['attack'] * $difficulty['monster_damage']);
        $monsterDefense = (int) ($monsterStats['defense'] * $difficulty['monster_damage']);

        $activeSkills = $character->skills()
            ->whereHas('skill', fn ($q) => $q->where('type', 'active'))
            ->with('skill')
            ->get();

        $isCrit = (rand(1, 100) / 100) <= $charCritRate;
        $baseDamage = max(1, $charAttack - $monsterDefense * 0.5);
        $skillDamage = 0;
        $skillsUsedThisRound = [];
        $newCooldowns = $skillCooldowns;

        foreach ($requestedSkillIds as $rid) {
            foreach ($activeSkills as $charSkill) {
                if ($charSkill->skill_id !== $rid) {
                    continue;
                }
                $skill = $charSkill->skill;
                $cooldownEnd = $newCooldowns[$rid] ?? 0;
                if ($currentMana >= $skill->mana_cost && $cooldownEnd <= $currentRound) {
                    $skillDamage = $skill->damage;
                    $currentMana -= $skill->mana_cost;
                    $newCooldowns[$rid] = $currentRound + (int) $skill->cooldown;
                    $skillsUsedThisRound[] = [
                        'skill_id' => $skill->id,
                        'name' => $skill->name,
                        'icon' => $skill->icon,
                    ];
                    break 2;
                }
                break;
            }
        }

        // 本回合双方伤害先算好，再同时扣血（双方每回合都会受到伤害）
        $damage = $skillDamage > 0
            ? (int) ($baseDamage + $skillDamage)
            : (int) ($baseDamage * ($isCrit ? $charCritDamage : 1));
        $monsterDamage = (int) max(1, $monsterAttack - $charDefense * 0.3);

        $roundDamageDealt = $damage;
        $roundDamageTaken = $monsterDamage;

        $charHp -= $monsterDamage;
        $monsterHp -= $damage;

        foreach ($skillsUsedThisRound as $entry) {
            $id = $entry['skill_id'];
            if (!isset($skillsUsedAggregated[$id])) {
                $skillsUsedAggregated[$id] = [
                    'skill_id' => $entry['skill_id'],
                    'name' => $entry['name'],
                    'icon' => $entry['icon'] ?? null,
                    'use_count' => 0,
                ];
            }
            $skillsUsedAggregated[$id]['use_count']++;
        }
        $newSkillsAggregated = array_values($skillsUsedAggregated);

        return [
            'round_damage_dealt' => $roundDamageDealt,
            'round_damage_taken' => $roundDamageTaken,
            'new_monster_hp' => max(0, $monsterHp),
            'new_char_hp' => $charHp,
            'new_char_mana' => $currentMana,
            'victory' => $monsterHp <= 0,
            'defeat' => $charHp <= 0,
            'skills_used_this_round' => $skillsUsedThisRound,
            'new_cooldowns' => $newCooldowns,
            'new_skills_aggregated' => $newSkillsAggregated,
        ];
    }

    /**
     * 创建掉落物品
     */
    private function createItem(GameCharacter $character, array $itemData): ?GameItem
    {
        // 查找物品定义
        $definition = GameItemDefinition::query()
            ->where('type', $itemData['type'])
            ->where('required_level', '<=', $itemData['level'])
            ->where('is_active', true)
            ->inRandomOrder()
            ->first();

        if (!$definition) {
            return null;
        }

        // 检查背包空间
        if ($character->items()->where('is_in_storage', false)->count() >= InventoryController::INVENTORY_SIZE) {
            return null;
        }

        $quality = $itemData['quality'];
        $qualityMultiplier = GameItem::QUALITY_MULTIPLIERS[$quality];
        $stats = [];
        foreach ($definition->base_stats ?? [] as $stat => $value) {
            $stats[$stat] = (int)($value * $qualityMultiplier * (0.8 + rand(0, 40) / 100));
        }

        // 词缀与插槽
        $affixes = [];
        $sockets = 0;
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
        $type = $potionData['sub_type'];
        $level = $potionData['level'];
        if (!isset($potionConfigs[$type][$level])) return null;
        $config = $potionConfigs[$type][$level];
        $statKey = $type === 'hp' ? 'max_hp' : 'max_mana';

        // 可叠加
        $existingPotion = $character->items()
            ->whereHas('definition', function ($query) use ($type) {
                $query->where('type', 'potion')->where('sub_type', $type);
            })
            ->where('is_in_storage', false)
            ->first();
        if ($existingPotion) {
            $existingPotion->increment('quantity');
            return $existingPotion->load('definition');
        }

        // 检查背包空间
        if ($character->items()->where('is_in_storage', false)->count() >= InventoryController::INVENTORY_SIZE) {
            return null;
        }

        // 查找或创建药水定义
        $definition = GameItemDefinition::query()
            ->where('type', 'potion')
            ->where('sub_type', $type)
            ->whereJsonContains('gem_stats->restore', $config['restore'])
            ->first();

        if (!$definition) {
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
                'description' => "恢复{$config['restore']}点" . ($type === 'hp' ? '生命值' : '法力值'),
                'is_active' => true,
                'sockets' => 0,
                'gem_stats' => ['restore' => $config['restore']],
            ]);
        }

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

        if ($character->items()->where('is_in_storage', false)->count() >= InventoryController::INVENTORY_SIZE) {
            return null;
        }

        // 简单定义，实际应用请以预设表为准
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

        // HP药水
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
                    $currentHp = $character->getCurrentHp();
                }
            }
        }

        // MP药水
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
        $statKey = $type === 'hp' ? 'max_hp' : 'max_mana';
        return $character->items()
            ->where('is_in_storage', false)
            ->whereHas('definition', function ($query) use ($type) {
                $query->where('type', 'potion')
                      ->where('sub_type', $type);
            })
            ->with('definition')
            ->get()
            ->sortByDesc(fn($item) => $item->definition->base_stats[$statKey] ?? 0)
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

        $potion->quantity > 1 ? $potion->decrement('quantity') : $potion->delete();
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
            if (!in_array($i, $usedSlots)) {
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

        try {
            $character = $this->getCharacter($request);

            $item = $character->items()
                ->where('id', $validated['item_id'])
                ->where('is_in_storage', false)
                ->with('definition')
                ->first();

            if (!$item) {
                return $this->error('物品不存在');
            }
            if ($item->definition->type !== 'potion') {
                return $this->error('该物品不是药品');
            }
            if ($item->quantity < 1) {
                return $this->error('药品数量不足');
            }

            $stats = $item->definition->base_stats ?? [];
            $hpRestored = $stats['max_hp'] ?? 0;
            $manaRestored = $stats['max_mana'] ?? 0;

            if ($hpRestored > 0) {
                $character->restoreHp($hpRestored);
            }
            if ($manaRestored > 0) {
                $character->restoreMana($manaRestored);
            }

            $item->quantity > 1 ? $item->decrement('quantity') : $item->delete();

            return $this->success([
                'current_hp' => $character->getCurrentHp(),
                'current_mana' => $character->getCurrentMana(),
                'max_hp' => $character->getMaxHp(),
                'max_mana' => $character->getMaxMana(),
                'message' => $hpRestored > 0 && $manaRestored > 0
                    ? "恢复了 {$hpRestored} 点生命值和 {$manaRestored} 点法力值"
                    : ($hpRestored > 0 ? "恢复了 {$hpRestored} 点生命值" : "恢复了 {$manaRestored} 点法力值"),
            ], '药品使用成功');
        } catch (Throwable $e) {
            return $this->error('使用药品失败', ['error' => $e->getMessage()]);
        }
    }

    /**
     * 获取角色
     */
    private function getCharacter(Request $request): GameCharacter
    {
        $characterId = $request->query('character_id') ?? $request->input('character_id');

        $query = GameCharacter::query()
            ->where('user_id', $request->user()->id);

        if ($characterId) {
            $query->where('id', $characterId);
        }

        return $query->firstOrFail();
    }
}
