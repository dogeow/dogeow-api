<?php

namespace App\Services\Game;

use App\Events\Game\GameCombatUpdate;
use App\Events\Game\GameInventoryUpdate;
use App\Models\Game\GameCharacter;
use App\Models\Game\GameMapDefinition;
use App\Models\Game\GameMonsterDefinition;
use Illuminate\Support\Facades\Log;

/**
 * 战斗服务类
 *
 * 负责战斗相关的业务逻辑，包括执行战斗回合、处理胜负、发放奖励等
 */
class GameCombatService
{
    /**
     * 构造函数
     *
     * @param  CombatRoundProcessor  $roundProcessor  回合处理器
     * @param  GameMonsterService  $monsterService  怪物服务
     * @param  GamePotionService  $potionService  药水服务
     * @param  GameCombatLootService  $lootService  战利品服务
     * @param  GameCombatLogService  $combatLogService  战斗日志服务
     */
    public function __construct(
        private readonly CombatRoundProcessor $roundProcessor,
        private readonly GameMonsterService $monsterService,
        private readonly GamePotionService $potionService,
        private readonly GameCombatLootService $lootService,
        private readonly GameCombatLogService $combatLogService
    ) {}

    /**
     * 检查怪物是否需要刷新
     */
    public function shouldRefreshMonsters(GameCharacter $character): bool
    {
        return $this->monsterService->shouldRefreshMonsters($character);
    }

    /**
     * 广播怪物出现（不处理攻击）
     */
    public function broadcastMonstersAppear(GameCharacter $character, GameMapDefinition $map): void
    {
        // 刷新怪物
        $this->monsterService->generateNewMonsters($character, $map, $character->combat_monsters ?? [], true);

        // 广播怪物出现
        $monsterData = $this->monsterService->formatMonstersForResponse($character);
        $monstersAppear = [
            'type' => 'monsters_appear',
            'monsters' => $monsterData['monsters'],
            'character' => [
                'current_hp' => $character->getCurrentHp(),
                'current_mana' => $character->getCurrentMana(),
            ],
        ];
        broadcast(new \App\Events\Game\GameCombatUpdate($character->id, $monstersAppear));
    }

    /**
     * 获取战斗状态
     *
     * @param  GameCharacter  $character  角色实例
     * @return array 战斗状态数据
     */
    public function getCombatStatus(GameCharacter $character): array
    {
        $character->initializeHpMana();

        $result = [
            'is_fighting' => $character->is_fighting,
            'current_map' => $character->currentMap,
            'combat_stats' => $character->getCombatStats(),
            'current_hp' => $character->getCurrentHp(),
            'current_mana' => $character->getCurrentMana(),
            'last_combat_at' => $character->last_combat_at,
            'skill_cooldowns' => $character->combat_skill_cooldowns ?? [],
        ];

        if ($character->is_fighting) {
            $monsters = $character->combat_monsters ?? [];
            $result['current_combat_monsters'] = $monsters;
            $firstAliveOrAny = null;
            foreach ($monsters as $m) {
                if (is_array($m) && isset($m['id'])) {
                    $firstAliveOrAny = $m;
                    if (($m['hp'] ?? 0) > 0) {
                        break;
                    }
                }
            }
            if ($firstAliveOrAny !== null) {
                $result['current_combat_monster'] = [
                    'id' => $firstAliveOrAny['id'],
                    'name' => $firstAliveOrAny['name'] ?? '',
                    'type' => $firstAliveOrAny['type'] ?? 'normal',
                    'level' => (int) ($firstAliveOrAny['level'] ?? 1),
                    'hp' => (int) ($firstAliveOrAny['hp'] ?? 0),
                    'max_hp' => (int) ($firstAliveOrAny['max_hp'] ?? 0),
                ];
            } elseif ($character->combat_monster_id !== null) {
                $def = GameMonsterDefinition::query()->find($character->combat_monster_id);
                if ($def) {
                    $result['current_combat_monster'] = [
                        'id' => $def->id,
                        'name' => $def->name,
                        'type' => $def->type ?? 'normal',
                        'level' => (int) $def->level,
                        'hp' => (int) ($character->combat_monster_hp ?? 0),
                        'max_hp' => (int) ($character->combat_monster_max_hp ?? 0),
                    ];
                }
            }
        }

        return $result;
    }

    /**
     * 更新药水设置
     *
     * @param  GameCharacter  $character  角色实例
     * @param  array  $settings  药水设置
     * @return GameCharacter 更新后的角色
     */
    public function updatePotionSettings(GameCharacter $character, array $settings): GameCharacter
    {
        foreach (['auto_use_hp_potion', 'hp_potion_threshold', 'auto_use_mp_potion', 'mp_potion_threshold'] as $key) {
            if (array_key_exists($key, $settings)) {
                $character->$key = $settings[$key];
            }
        }
        $character->save();

        return $character;
    }

    /**
     * 执行一轮战斗（支持多怪物连续战斗）
     *
     * @param  GameCharacter  $character  角色实例
     * @param  array  $skillIds  使用的技能ID数组
     * @return array 战斗结果
     *
     * @throws \InvalidArgumentException 地图不存在或没有怪物
     * @throws \RuntimeException 血量不足或战斗结束
     */
    public function executeRound(GameCharacter $character, array $skillIds = []): array
    {
        // 检查是否选择了地图
        if (! $character->current_map_id) {
            throw new \InvalidArgumentException('请先选择一个地图');
        }

        // 如果不在战斗中，自动开始战斗
        if (! $character->is_fighting) {
            $character->fill([
                'is_fighting' => true,
                'last_combat_at' => now(),
            ])->save();
        }

        // 初始化HP和Mana
        $character->initializeHpMana();
        $currentHp = $character->getCurrentHp();
        $currentMana = $character->getCurrentMana();

        // 检查血量是否不足
        if ($currentHp <= 0) {
            $character->clearCombatState();
            $character->update(['is_fighting' => false]);
            throw new \RuntimeException('角色血量不足，已自动停止战斗', previous: new \Exception(json_encode([
                'auto_stopped' => true,
                'current_hp' => $currentHp,
            ])));
        }

        $map = $character->currentMap;
        if (! $map) {
            throw new \InvalidArgumentException('地图不存在');
        }

        // 准备怪物信息
        $monsterInfo = $this->monsterService->prepareMonsterInfo($character, $map);
        $monster = $monsterInfo[0];
        $monsterLevel = $monsterInfo[1];
        $monsterHp = $monsterInfo[3];
        $monsterMaxHp = $monsterInfo[4];

        // 检查怪物是否存在
        if (! $monster) {
            throw new \InvalidArgumentException('当前战斗怪物不存在，已清除状态');
        }
        if ($monster === 'no-monster') {
            $character->update(['is_fighting' => false]);
            throw new \RuntimeException('该地图没有怪物，已自动停止战斗，请选择其他地图', previous: new \Exception(json_encode([
                'auto_stopped' => true,
            ])));
        }

        // 处理回合
        $currentRound = (int) $character->combat_rounds + 1;
        $skillCooldowns = $character->combat_skill_cooldowns ?? [];
        $skillsUsedAggregated = $character->combat_skills_used ?? [];
        $requestedSkillIds = array_map('intval', array_values($skillIds));

        $roundResult = $this->roundProcessor->processOneRound(
            $character,
            $currentRound,
            $skillCooldowns,
            $skillsUsedAggregated,
            $requestedSkillIds
        );

        // 回合后自动使用药水
        $charStats = $character->getCombatStats();
        $potionUsed = $this->potionService->tryAutoUsePotions($character, $roundResult['new_char_hp'], $roundResult['new_char_mana'], $charStats);
        if (! empty($potionUsed)) {
            $roundResult['new_char_hp'] = $character->getCurrentHp();
            $roundResult['new_char_mana'] = $character->getCurrentMana();
        }

        // 持久化战斗状态
        $this->persistCombatState($character, $roundResult, $currentRound);

        // 处理失败
        if ($roundResult['defeat']) {
            return $this->handleDefeat($character, $map, $monster, $monsterLevel, $monsterMaxHp, $currentRound, $roundResult, $monsterHp);
        }

        // 检查是否所有怪物都死亡
        $isVictory = ! ($roundResult['has_alive_monster'] ?? true);
        if ($isVictory) {
            // 所有怪物死亡，不立即重生，保持死亡怪物可见直到下一回合
            $roundResult['new_monster_max_hp'] = $roundResult['new_monster_hp']; // 保持总HP不变
            $roundResult['victory'] = true;
        }

        // 每回合按概率尝试补充新怪物（30% 不生成，70% 按权重生成 1～5 只），不要求全部死亡
        $roundResult = $this->monsterService->tryAddNewMonsters($character, $map, $roundResult, $currentRound);

        // 为本回合死亡的怪物发放经验和铜币
        $expGained = $roundResult['experience_gained'] ?? 0;
        $copperGained = $roundResult['copper_gained'] ?? 0;
        if ($expGained > 0 || $copperGained > 0) {
            $rewards = $this->lootService->distributeRewards($character, $roundResult);
            $roundResult['loot'] = array_merge($roundResult['loot'] ?? [], ['copper' => $copperGained]);
        }

        // 处理死亡战利品
        $deathLoot = $this->lootService->processDeathLoot($character, $roundResult);
        if (! empty($deathLoot)) {
            $roundResult['loot'] = array_merge($roundResult['loot'] ?? [], $deathLoot);
        }

        // 保存更新的怪物状态
        $character->combat_monster_hp = max(0, $roundResult['new_monster_hp']);
        $character->combat_monster_max_hp = max(0, $roundResult['new_monster_max_hp'] ?? $roundResult['new_monster_hp']);
        $character->save();

        // 获取当前怪物列表用于响应（固定5个槽位）
        $monsterData = $this->monsterService->formatMonstersForResponse($character);
        $fixedMonsters = $monsterData['monsters'];
        $firstAliveMonster = $monsterData['first_alive_monster'];

        // 创建战斗日志
        $combatLog = $this->combatLogService->createRoundLog(
            $character,
            $map,
            $firstAliveMonster['id'] ?? $monster->id,
            $roundResult,
            null, // potionUsedBeforeRound
            $potionUsed // potionUsedAfterRound
        );

        $result = [
            'victory' => $roundResult['victory'] ?? false,
            'defeat' => false,
            'monster_id' => $firstAliveMonster['id'] ?? $monster->id,
            'monsters' => $fixedMonsters,
            'monster' => [
                'name' => $firstAliveMonster['name'] ?? $monster->name,
                'type' => $firstAliveMonster['type'] ?? $monster->type,
                'level' => $firstAliveMonster['level'] ?? $monsterLevel,
                'hp' => max(0, $roundResult['new_monster_hp'] ?? $monsterHp),
                'max_hp' => max(0, $roundResult['new_monster_max_hp'] ?? $monsterMaxHp),
            ],
            'monster_hp_before_round' => $monsterHp,
            'damage_dealt' => $roundResult['round_damage_dealt'],
            'damage_taken' => $roundResult['round_damage_taken'],
            'rounds' => $currentRound,
            'experience_gained' => $roundResult['experience_gained'] ?? 0,
            'copper_gained' => $roundResult['copper_gained'] ?? 0,
            'loot' => $roundResult['loot'] ?? [],
            'potion_used' => [
                'before' => $potionUsedBeforeRound ?? [],
                'after' => $potionUsed ?? [],
            ],
            'skills_used' => $roundResult['skills_used_this_round'],
            'skill_target_positions' => $roundResult['skill_target_positions'] ?? [],
            'skill_cooldowns' => $character->combat_skill_cooldowns ?? [], // 技能冷却（回合数）
            'character' => $character->fresh()->toArray(),
            'combat_log_id' => $combatLog->id,
        ];

        // 广播战斗更新
        broadcast(new GameCombatUpdate($character->id, $result));
        $character->refresh();

        // 广播背包更新
        $inventoryPayload = app(GameInventoryService::class)->getInventoryForBroadcast($character);
        Log::info('游戏背包广播数据', [
            'character_id' => $character->id,
            'inventory_count' => count($inventoryPayload['inventory'] ?? []),
            'first_items_quantity' => array_map(fn ($i) => ['id' => $i['id'] ?? null, 'quantity' => $i['quantity'] ?? null], array_slice($inventoryPayload['inventory'] ?? [], 0, 3)),
        ]);
        broadcast(new GameInventoryUpdate($character->id, $inventoryPayload));

        return $result;
    }

    /**
     * 持久化战斗状态
     *
     * @param  GameCharacter  $character  角色实例
     * @param  array  $roundResult  回合结果
     * @param  int  $currentRound  当前回合数
     */
    private function persistCombatState(GameCharacter $character, array $roundResult, int $currentRound): void
    {
        $character->current_hp = max(0, $roundResult['new_char_hp']);
        $character->current_mana = max(0, $roundResult['new_char_mana']);
        $character->combat_total_damage_dealt += $roundResult['round_damage_dealt'];
        $character->combat_total_damage_taken += $roundResult['round_damage_taken'];
        $character->combat_rounds = $currentRound;
        $character->combat_skills_used = $roundResult['new_skills_aggregated'];
        $character->combat_skill_cooldowns = $roundResult['new_cooldowns'];

        // 保存更新的怪物数组
        if (isset($roundResult['monsters_updated'])) {
            $character->combat_monsters = $roundResult['monsters_updated'];
        }
    }

    /**
     * 处理失败情况
     *
     * @param  GameCharacter  $character  角色实例
     * @param  GameMapDefinition  $map  地图实例
     * @param  GameMonsterDefinition  $monster  怪物实例
     * @param  int  $monsterLevel  怪物等级
     * @param  int  $monsterMaxHp  怪物最大生命值
     * @param  int  $currentRound  当前回合数
     * @param  array  $roundResult  回合结果
     * @param  int  $monsterHpBeforeRound  回合前怪物生命值
     * @return array 失败结果
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
    ): array {
        // 失败时
        $character->current_hp = max(0, $roundResult['new_char_hp']);
        $character->is_fighting = false;

        // 创建失败日志
        $combatLog = $this->combatLogService->createDefeatLog($character, $map, $monster, $roundResult, $currentRound);

        // 清除战斗状态并保存
        $character->clearCombatState();
        $character->save();

        // 调试：保存后检查值
        Log::info('[handleDefeat] 保存后数据库中的current_hp:', [
            'character_id' => $character->id,
            'current_hp' => $character->current_hp,
            'fresh_current_hp' => $character->fresh()->current_hp,
        ]);

        $freshCharacter = $character->fresh();
        Log::info('[handleDefeat] 刷新后的角色:', ['current_hp' => $freshCharacter->current_hp]);
        $charArray = $freshCharacter->toArray();
        $charArray['current_hp'] = 0;
        $charArray['current_mana'] = 0;
        Log::info('[handleDefeat] 覆盖后的charArray:', ['current_hp' => $charArray['current_hp']]);

        $result = [
            'victory' => false,
            'defeat' => true,
            'auto_stopped' => true,
            'monster_id' => $monster->id,
            'monsters' => [],
            'monster' => [
                'name' => $monster->name,
                'type' => $monster->type,
                'level' => $monsterLevel,
                'hp' => max(0, $roundResult['new_monster_hp'] ?? $monsterHpBeforeRound),
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
            'character' => $charArray,
            'current_hp' => 0,
            'current_mana' => 0,
            'combat_log_id' => $combatLog->id,
        ];

        // 广播战斗更新
        broadcast(new GameCombatUpdate($character->id, $result));
        $character->refresh();

        // 广播背包更新
        $inventoryPayload = app(GameInventoryService::class)->getInventoryForBroadcast($character);
        Log::info('游戏背包广播数据 (handleDefeat)', [
            'character_id' => $character->id,
            'first_items_quantity' => array_map(fn ($i) => ['id' => $i['id'] ?? null, 'quantity' => $i['quantity'] ?? null], array_slice($inventoryPayload['inventory'] ?? [], 0, 3)),
        ]);
        broadcast(new GameInventoryUpdate($character->id, $inventoryPayload));

        return $result;
    }

    /**
     * 获取战斗日志
     *
     * @param  GameCharacter  $character  角色实例
     * @return array 战斗日志列表
     */
    public function getCombatLogs(GameCharacter $character): array
    {
        return $this->combatLogService->getCombatLogs($character);
    }

    /**
     * 获取单条战斗日志详情
     *
     * @param  GameCharacter  $character  角色实例
     * @param  int  $logId  日志ID
     * @return array 日志详情
     */
    public function getCombatLogDetail(GameCharacter $character, int $logId): array
    {
        return $this->combatLogService->getCombatLogDetail($character, $logId);
    }

    /**
     * 获取战斗统计
     *
     * @param  GameCharacter  $character  角色实例
     * @return array 战斗统计数据
     */
    public function getCombatStats(GameCharacter $character): array
    {
        return $this->combatLogService->getCombatStats($character);
    }
}
