<?php

namespace App\Services\Game;

use App\Events\Game\GameCombatUpdate;
use App\Models\Game\GameCharacter;
use App\Models\Game\GameCombatLog;
use App\Models\Game\GameItem;
use App\Models\Game\GameItemDefinition;
use App\Models\Game\GameMapDefinition;
use App\Models\Game\GameMonsterDefinition;

class GameCombatService
{
    public function __construct(
        private readonly CombatRoundProcessor $roundProcessor
    ) {}

    /**
     * 获取战斗状态
     */
    public function getCombatStatus(GameCharacter $character): array
    {
        $character->initializeHpMana();

        return [
            'is_fighting' => $character->is_fighting,
            'current_map' => $character->currentMap,
            'combat_stats' => $character->getCombatStats(),
            'current_hp' => $character->getCurrentHp(),
            'current_mana' => $character->getCurrentMana(),
            'last_combat_at' => $character->last_combat_at,
        ];
    }

    /**
     * 更新药水设置
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
     * 执行一回合战斗（支持多怪物持续战斗）
     */
    public function executeRound(GameCharacter $character, array $skillIds = []): array
    {
        if (! $character->current_map_id) {
            throw new \InvalidArgumentException('请先选择一个地图');
        }

        // 如果不在战斗状态，自动开始战斗
        if (! $character->is_fighting) {
            $character->fill([
                'is_fighting' => true,
                'last_combat_at' => now(),
            ])->save();
        }

        $character->initializeHpMana();
        $currentHp = $character->getCurrentHp();
        $currentMana = $character->getCurrentMana();

        // 自动使用药水
        $charStats = $character->getCombatStats();
        $potionUsedBeforeRound = $this->tryAutoUsePotions($character, $currentHp, $currentMana, $charStats);
        if (! empty($potionUsedBeforeRound)) {
            $currentHp = $character->getCurrentHp();
        }

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
        [
            $monster,
            $monsterLevel,
            $monsterStats,
            $monsterHp,
            $monsterMaxHp
        ] = $this->prepareMonsterInfo($character, $map);

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
            $monsterStats,
            $currentRound,
            $skillCooldowns,
            $skillsUsedAggregated,
            $requestedSkillIds
        );

        // 回合后自动使用药水
        $charStats = $character->getCombatStats();
        $potionUsed = $this->tryAutoUsePotions($character, $roundResult['new_char_hp'], $roundResult['new_char_mana'], $charStats);
        if (! empty($potionUsed)) {
            $roundResult['new_char_hp'] = $character->getCurrentHp();
            $roundResult['new_char_mana'] = $character->getCurrentMana();
        }

        // 持久化状态
        $this->persistCombatState($character, $roundResult, $currentRound);

        // 处理战斗结果
        if ($roundResult['defeat']) {
            return $this->handleDefeat($character, $map, $monster, $monsterLevel, $monsterMaxHp, $currentRound, $roundResult, $monsterHp);
        }

        // 检查是否所有怪物都死了
        if (! ($roundResult['has_alive_monster'] ?? true)) {
            // 所有怪物死亡，不立即补充，保留死亡怪物显示到下一回合
            $roundResult['new_monster_max_hp'] = $roundResult['new_monster_hp']; // 保持总血量不变
        } else {
            // 每回合有一定概率加入新怪物（最多5只）
            $roundResult = $this->tryAddNewMonsters($character, $map, $roundResult, $currentRound);
        }

        // 本回合有怪物死亡时发放经验与铜币（由 processOneRound 已算好，仅本回合死亡的怪物）
        $expGained = $roundResult['experience_gained'] ?? 0;
        $copperGained = $roundResult['copper_gained'] ?? 0;
        if ($expGained > 0) {
            $character->addExperience($expGained);
        }
        if ($copperGained > 0) {
            $character->copper += $copperGained;
        }
        if ($expGained > 0 || $copperGained > 0) {
            $roundResult['loot'] = array_merge($roundResult['loot'] ?? [], ['copper' => $copperGained]);
        }

        // 保存更新后的怪物状态
        $character->combat_monster_hp = max(0, $roundResult['new_monster_hp']);
        $character->combat_monster_max_hp = max(0, $roundResult['new_monster_max_hp'] ?? $roundResult['new_monster_hp']);
        $character->save();

        // 获取当前怪物信息用于返回（固定 5 槽位，空槽为 null）
        $currentMonsters = $character->combat_monsters ?? [];
        $fixedMonsters = array_fill(0, 5, null);
        for ($idx = 0; $idx < 5; $idx++) {
            $m = $currentMonsters[$idx] ?? null;
            if (is_array($m)) {
                $m['position'] = $idx;
                $fixedMonsters[$idx] = $m;
            }
        }

        // 找出第一只存活的怪物
        $firstAliveMonster = null;
        foreach ($fixedMonsters as $m) {
            if ($m && ($m['hp'] ?? 0) > 0) {
                $firstAliveMonster = $m;
                break;
            }
        }
        if (! $firstAliveMonster) {
            $firstAliveMonster = ['name' => '怪物', 'type' => 'normal', 'level' => 1];
        }

        // 创建战斗日志（每回合都记录）
        $combatLog = GameCombatLog::create([
            'character_id' => $character->id,
            'map_id' => $map->id,
            'monster_id' => $firstAliveMonster['id'] ?? $monster->id,
            'damage_dealt' => $roundResult['round_damage_dealt'],
            'damage_taken' => $roundResult['round_damage_taken'],
            'victory' => false,
            'loot_dropped' => ! empty($roundResult['loot']) ? $roundResult['loot'] : null,
            'experience_gained' => $roundResult['experience_gained'] ?? 0,
            'copper_gained' => $roundResult['copper_gained'] ?? 0,
            'duration_seconds' => 0,
            'skills_used' => $roundResult['skills_used_this_round'],
            'potion_used' => array_merge($potionUsedBeforeRound ?? [], $potionUsed ?? []) ?: null,
        ]);

        $result = [
            'victory' => false,
            'defeat' => false,
            'monster_id' => $firstAliveMonster['id'] ?? $monster->id,
            'monsters' => $fixedMonsters, // 返回固定5个位置的怪物数组
            'monster' => [
                'name' => $firstAliveMonster['name'] ?? $monster->name,
                'type' => $firstAliveMonster['type'] ?? $monster->type,
                'level' => $firstAliveMonster['level'] ?? $monsterLevel,
                'hp' => max(0, $roundResult['new_monster_hp']),
                'max_hp' => max(0, $roundResult['new_monster_max_hp']),
            ],
            'monster_hp_before_round' => $monsterHp,
            'damage_dealt' => $roundResult['round_damage_dealt'],
            'damage_taken' => $roundResult['round_damage_taken'],
            'rounds' => $currentRound,
            'experience_gained' => $roundResult['experience_gained'] ?? 0,
            'copper_gained' => $roundResult['copper_gained'] ?? 0,
            'loot' => $roundResult['loot'] ?? [],
            'potion_used' => array_merge($potionUsedBeforeRound ?? [], $potionUsed ?? []),
            'skills_used' => $roundResult['skills_used_this_round'],
            'skill_target_positions' => $roundResult['skill_target_positions'] ?? [],
            'character' => $character->fresh()->toArray(),
            'combat_log_id' => $combatLog->id,
        ];
        broadcast(new GameCombatUpdate($character->id, $result));

        return $result;
    }

    /**
     * 补充怪物（当所有怪物死亡时），并发放奖励
     */
    private function respawnMonsters(GameCharacter $character, GameMapDefinition $map, array $roundResult, int $currentRound): array
    {
        $difficulty = $character->getDifficultyMultipliers();
        $monsters = $map->getMonsters();

        if (empty($monsters)) {
            return $roundResult;
        }

        // 获取上一轮死掉的怪物，计算经验和掉落
        $oldMonsters = $character->combat_monsters ?? [];
        $totalExperience = 0;
        $totalCopper = 0;
        $loot = ['copper' => 0];

        foreach ($oldMonsters as $m) {
            if (! is_array($m)) {
                continue;
            }
            $totalExperience += $m['experience'] ?? 0;
            $totalCopper += rand(1, 10);
        }

        // 应用难度倍率
        $totalExperience = (int) ($totalExperience * $difficulty['reward']);
        $totalCopper = (int) ($totalCopper * $difficulty['reward']);

        // 发放经验
        if ($totalExperience > 0) {
            $character->addExperience($totalExperience);
        }

        // 发放金币
        if ($totalCopper > 0) {
            $character->copper += $totalCopper;
            $loot['copper'] = $totalCopper;
        }

        // 随机掉落物品（低概率）
        if (! empty($monsters)) {
            $baseMonster = $monsters[array_rand($monsters)];
            $lootResult = $baseMonster->generateLoot($character->level);
            if (isset($lootResult['item'])) {
                $item = $this->createItem($character, $lootResult['item']);
                if ($item) {
                    $loot['item'] = $item;
                }
            }
            if (isset($lootResult['potion'])) {
                $potion = $this->createPotion($character, $lootResult['potion']);
                if ($potion) {
                    $loot['potion'] = $potion;
                }
            }
        }

        // 随机生成 1-3 只新怪物，放入随机槽位（固定 5 槽位）
        $newCount = rand(1, 3);
        $baseMonster = $monsters[array_rand($monsters)];
        $baseLevel = rand(
            max($map->min_level, $baseMonster->level - 3),
            min($map->max_level, $baseMonster->level + 3)
        );

        $monsterDataList = [];
        for ($i = 0; $i < $newCount; $i++) {
            $level = $baseLevel + rand(-1, 1);
            $level = max(1, $level);
            $stats = $baseMonster->getCombatStats($level);
            $maxHp = (int) ($stats['hp'] * $difficulty['monster_hp']);

            $monsterDataList[] = [
                'id' => $baseMonster->id,
                'name' => $baseMonster->name,
                'type' => $baseMonster->type,
                'level' => $level,
                'hp' => $maxHp,
                'max_hp' => $maxHp,
                'attack' => (int) ($stats['attack'] * $difficulty['monster_damage']),
                'defense' => (int) ($stats['defense'] * $difficulty['monster_damage']),
                'experience' => (int) ($stats['experience'] * $difficulty['reward']),
            ];
        }

        $slotIndices = [0, 1, 2, 3, 4];
        shuffle($slotIndices);
        $newMonsters = array_fill(0, 5, null);
        foreach ($monsterDataList as $idx => $data) {
            $slot = $slotIndices[$idx];
            $data['position'] = $slot;
            $newMonsters[$slot] = $data;
        }

        $character->combat_monsters = $newMonsters;
        $roundResult['new_monster_hp'] = array_sum(array_column($monsterDataList, 'hp'));
        $roundResult['new_monster_max_hp'] = array_sum(array_column($monsterDataList, 'max_hp'));
        $roundResult['experience_gained'] = $totalExperience;
        $roundResult['copper_gained'] = $totalCopper;
        $roundResult['loot'] = $loot;

        return $roundResult;
    }

    /**
     * 尝试添加新怪物（每回合概率加入，最多5只）
     */
    private function tryAddNewMonsters(GameCharacter $character, GameMapDefinition $map, array $roundResult, int $currentRound): array
    {
        $currentMonsters = $character->combat_monsters ?? [];
        if (count($currentMonsters) !== 5) {
            $currentMonsters = array_fill(0, 5, null);
        }
        $monsterCount = count(array_filter($currentMonsters, 'is_array'));

        if ($monsterCount >= 5) {
            $roundResult['new_monster_hp'] = array_sum(array_column(array_filter($currentMonsters, 'is_array'), 'hp'));
            $roundResult['new_monster_max_hp'] = array_sum(array_column(array_filter($currentMonsters, 'is_array'), 'max_hp'));

            return $roundResult;
        }

        if (rand(1, 100) > 30) {
            $roundResult['new_monster_hp'] = array_sum(array_column(array_filter($currentMonsters, 'is_array'), 'hp'));
            $roundResult['new_monster_max_hp'] = array_sum(array_column(array_filter($currentMonsters, 'is_array'), 'max_hp'));

            return $roundResult;
        }

        $difficulty = $character->getDifficultyMultipliers();
        $monsters = $map->getMonsters();

        if (empty($monsters)) {
            return $roundResult;
        }

        $emptySlots = [];
        for ($i = 0; $i < 5; $i++) {
            if (! isset($currentMonsters[$i]) || $currentMonsters[$i] === null || ! is_array($currentMonsters[$i])) {
                $emptySlots[] = $i;
            }
        }
        $canAdd = count($emptySlots);
        $addCount = min($canAdd, rand(1, 2));
        if ($addCount <= 0) {
            return $roundResult;
        }

        shuffle($emptySlots);
        $slotsToFill = array_slice($emptySlots, 0, $addCount);

        $baseMonster = $monsters[array_rand($monsters)];
        $baseLevel = rand(
            max($map->min_level, $baseMonster->level - 3),
            min($map->max_level, $baseMonster->level + 3)
        );

        foreach ($slotsToFill as $slot) {
            $level = $baseLevel + rand(-1, 1);
            $level = max(1, $level);
            $stats = $baseMonster->getCombatStats($level);
            $maxHp = (int) ($stats['hp'] * $difficulty['monster_hp']);

            $currentMonsters[$slot] = [
                'id' => $baseMonster->id,
                'name' => $baseMonster->name,
                'type' => $baseMonster->type,
                'level' => $level,
                'hp' => $maxHp,
                'max_hp' => $maxHp,
                'attack' => (int) ($stats['attack'] * $difficulty['monster_damage']),
                'defense' => (int) ($stats['defense'] * $difficulty['monster_damage']),
                'experience' => (int) ($stats['experience'] * $difficulty['reward']),
                'position' => $slot,
            ];
        }

        $character->combat_monsters = $currentMonsters;
        $roundResult['new_monster_hp'] = array_sum(array_column(array_filter($currentMonsters, 'is_array'), 'hp'));
        $roundResult['new_monster_max_hp'] = array_sum(array_column(array_filter($currentMonsters, 'is_array'), 'max_hp'));

        return $roundResult;
    }

    /**
     * 准备怪物信息（支持多怪物）
     */
    private function prepareMonsterInfo(GameCharacter $character, GameMapDefinition $map): array
    {
        $existingMonsters = $character->combat_monsters ?? [];

        // 检查是否有存活怪物
        $hasAliveMonster = false;
        foreach ($existingMonsters as $m) {
            if (is_array($m) && ($m['hp'] ?? 0) > 0) {
                $hasAliveMonster = true;
                break;
            }
        }

        if ($character->hasActiveCombat() && $hasAliveMonster) {
            // 继续已有战斗
            return $this->loadExistingMonsters($character, $existingMonsters);
        } else {
            // 开始新战斗，生成 1-5 只怪物
            return $this->generateNewMonsters($character, $map, $existingMonsters);
        }
    }

    /**
     * 加载已有怪物
     */
    private function loadExistingMonsters(GameCharacter $character, array $existingMonsters): array
    {
        $firstMonster = null;
        $monsterLevel = null;
        $monsterStats = null;
        $totalHp = 0;
        $totalMaxHp = 0;

        foreach ($existingMonsters as $m) {
            if (! is_array($m)) {
                continue;
            }
            if ($firstMonster === null && ($m['hp'] ?? 0) > 0) {
                $monster = GameMonsterDefinition::query()->find($m['id']);
                if ($monster) {
                    $firstMonster = $monster;
                    $monsterLevel = $m['level'];
                    $monsterStats = $monster->getCombatStats($monsterLevel);
                }
            }
            $totalHp += $m['hp'] ?? 0;
            $totalMaxHp += $m['max_hp'] ?? 0;
        }

        if (! $firstMonster) {
            $character->clearCombatState();

            return [null, null, null, null, null];
        }

        return [$firstMonster, $monsterLevel, $monsterStats, $totalHp, $totalMaxHp];
    }

    /**
     * 生成新怪物（1-5只）
     */
    private function generateNewMonsters(GameCharacter $character, GameMapDefinition $map, array $existingMonsters): array
    {
        $monsters = $map->getMonsters();
        if (empty($monsters)) {
            return ['no-monster', null, null, null, null];
        }

        $difficulty = $character->getDifficultyMultipliers();
        $newMonsters = [];

        // 随机生成 1-5 只怪物
        $monsterCount = rand(1, 5);
        $baseMonster = $monsters[array_rand($monsters)];
        $baseLevel = rand(
            max($map->min_level, $baseMonster->level - 3),
            min($map->max_level, $baseMonster->level + 3)
        );

        $monsterDataList = [];
        for ($i = 0; $i < $monsterCount; $i++) {
            $level = $baseLevel + rand(-1, 1);
            $level = max(1, $level);
            $stats = $baseMonster->getCombatStats($level);
            $maxHp = (int) ($stats['hp'] * $difficulty['monster_hp']);

            $monsterDataList[] = [
                'id' => $baseMonster->id,
                'name' => $baseMonster->name,
                'type' => $baseMonster->type,
                'level' => $level,
                'hp' => $maxHp,
                'max_hp' => $maxHp,
                'attack' => (int) ($stats['attack'] * $difficulty['monster_damage']),
                'defense' => (int) ($stats['defense'] * $difficulty['monster_damage']),
                'experience' => (int) ($stats['experience'] * $difficulty['reward']),
            ];
        }

        // 固定 5 个槽位（0-4），随机选 N 个槽位放怪物，其余为 null
        $slotIndices = [0, 1, 2, 3, 4];
        shuffle($slotIndices);
        $newMonsters = array_fill(0, 5, null);
        foreach ($monsterDataList as $idx => $data) {
            $slot = $slotIndices[$idx];
            $data['position'] = $slot;
            $newMonsters[$slot] = $data;
        }

        // 持久化怪物数组（5 槽位，可能含 null）
        $character->combat_monsters = $newMonsters;
        $character->combat_monster_id = $baseMonster->id;
        $character->combat_monster_level = $baseLevel;
        $character->combat_monster_hp = array_sum(array_column(array_filter($newMonsters, 'is_array'), 'hp'));
        $character->combat_monster_max_hp = array_sum(array_column(array_filter($newMonsters, 'is_array'), 'max_hp'));
        $character->combat_total_damage_dealt = 0;
        $character->combat_total_damage_taken = 0;
        $character->combat_rounds = 0;
        $character->combat_skills_used = null;
        $character->combat_skill_cooldowns = null;
        $character->combat_started_at = now();
        $character->save();

        // 第一只怪：槽位顺序下第一个存活的怪物
        $firstMonster = null;
        for ($slot = 0; $slot < 5; $slot++) {
            $m = $newMonsters[$slot] ?? null;
            if (is_array($m) && ($m['hp'] ?? 0) > 0) {
                $firstMonster = $m;
                break;
            }
        }
        $firstMonster = $firstMonster ?? $monsterDataList[0] ?? null;
        $monster = GameMonsterDefinition::query()->find($firstMonster['id']);
        $monsterStats = $monster?->getCombatStats($firstMonster['level']);

        return [
            $monster,
            $firstMonster['level'],
            $monsterStats,
            $character->combat_monster_hp,
            $character->combat_monster_max_hp,
        ];
    }

    /**
     * 持久化战斗状态（支持多怪物）
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
     * 处理失败
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
        $copperLoss = (int) ($character->copper * 0.1);
        $character->copper -= $copperLoss;
        // 阵亡时
        $character->current_hp = max(0, $roundResult['new_char_hp']);
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

        // 调试：查看 save 后的值
        \Log::info('[handleDefeat] after save, current_hp in DB:', [
            'character_id' => $character->id,
            'current_hp' => $character->current_hp,
            'fresh_current_hp' => $character->fresh()->current_hp,
        ]);

        $freshCharacter = $character->fresh();
        \Log::info('[handleDefeat] fresh character:', ['current_hp' => $freshCharacter->current_hp]);
        $charArray = $freshCharacter->toArray();
        $charArray['current_hp'] = 0;
        $charArray['current_mana'] = 0;
        \Log::info('[handleDefeat] charArray after override:', ['current_hp' => $charArray['current_hp']]);

        $result = [
            'victory' => false,
            'defeat' => true,
            'auto_stopped' => true,
            'monster_id' => $monster->id,
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
            'character' => $charArray,
            'current_hp' => 0,
            'current_mana' => 0,
            'combat_log_id' => $combatLog->id,
        ];
        broadcast(new GameCombatUpdate($character->id, $result));

        return $result;
    }

    /**
     * 获取战斗日志
     */
    public function getCombatLogs(GameCharacter $character): array
    {
        $logs = $character->combatLogs()
            ->with(['monster', 'map'])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return ['logs' => $logs];
    }

    /**
     * 获取战斗统计
     */
    public function getCombatStats(GameCharacter $character): array
    {
        $combatLogs = $character->combatLogs();

        return [
            'stats' => [
                'total_battles' => $combatLogs->count(),
                'total_victories' => (clone $combatLogs)->where('victory', true)->count(),
                'total_defeats' => (clone $combatLogs)->where('victory', false)->count(),
                'total_damage_dealt' => $combatLogs->sum('damage_dealt'),
                'total_damage_taken' => $combatLogs->sum('damage_taken'),
                'total_experience_gained' => $combatLogs->sum('experience_gained'),
                'total_copper_gained' => $combatLogs->sum('copper_gained'),
                'total_items_looted' => (clone $combatLogs)->whereNotNull('loot_dropped')->count(),
            ],
        ];
    }

    /**
     * 尝试自动使用药水
     */
    private function tryAutoUsePotions(GameCharacter $character, int $currentHp, int $currentMana, array $charStats): array
    {
        $used = [];

        $hpThreshold = (int) ($character->hp_potion_threshold ?? 30);
        $hpThreshold = max(1, min(100, $hpThreshold));
        if ($character->auto_use_hp_potion && ($charStats['max_hp'] ?? 0) > 0) {
            $hpPercent = ($currentHp / $charStats['max_hp']) * 100;
            if ($hpPercent <= $hpThreshold) {
                $potion = $this->findBestPotion($character, 'hp');
                if ($potion) {
                    $this->usePotionItem($character, $potion);
                    $used['hp'] = [
                        'name' => $potion->definition->name,
                        'restored' => $potion->definition->base_stats['max_hp'] ?? 0,
                    ];
                }
            }
        }

        $mpThreshold = (int) ($character->mp_potion_threshold ?? 30);
        $mpThreshold = max(1, min(100, $mpThreshold));
        if ($character->auto_use_mp_potion && ($charStats['max_mana'] ?? 0) > 0) {
            $mpPercent = ($currentMana / $charStats['max_mana']) * 100;
            if ($mpPercent <= $mpThreshold) {
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
            ->sortByDesc(fn ($item) => $item->definition->base_stats[$statKey] ?? 0)
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
     * 创建掉落物品
     */
    private function createItem(GameCharacter $character, array $itemData): ?GameItem
    {
        $definition = GameItemDefinition::query()
            ->where('type', $itemData['type'])
            ->where('required_level', '<=', $itemData['level'])
            ->where('is_active', true)
            ->inRandomOrder()
            ->first();

        if (! $definition) {
            return null;
        }

        $inventoryService = new GameInventoryService;
        if ($character->items()->where('is_in_storage', false)->count() >= $inventoryService::INVENTORY_SIZE) {
            return null;
        }

        $quality = $itemData['quality'];
        $qualityMultiplier = GameItem::QUALITY_MULTIPLIERS[$quality];
        $stats = [];
        foreach ($definition->base_stats ?? [] as $stat => $value) {
            $statValue = (int) ($value * $qualityMultiplier * (0.8 + rand(0, 40) / 100));
            if ($statValue !== 0) {
                $stats[$stat] = $statValue;
            }
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

        $basePrice = $definition->base_stats['price'] ?? 10;
        $sellPrice = (int) ($basePrice * $qualityMultiplier * 0.5);

        $item = GameItem::create([
            'character_id' => $character->id,
            'definition_id' => $definition->id,
            'quality' => $quality,
            'stats' => $stats,
            'affixes' => $affixes,
            'is_in_storage' => false,
            'quantity' => 1,
            'slot_index' => $inventoryService->findEmptySlot($character, false),
            'sockets' => $sockets,
            'sell_price' => $sellPrice,
        ]);

        return $item->load('definition');
    }

    /**
     * 创建掉落药水
     */
    private function createPotion(GameCharacter $character, array $potionData): ?GameItem
    {
        $potionConfigs = [
            'hp' => [
                'minor' => ['name' => '轻型生命药水', 'restore' => 50],
                'light' => ['name' => '生命药水', 'restore' => 100],
                'medium' => ['name' => '强效生命药水', 'restore' => 200],
                'full' => ['name' => '超级生命药水', 'restore' => 400],
            ],
            'mp' => [
                'minor' => ['name' => '轻型法力药水', 'restore' => 30],
                'light' => ['name' => '法力药水', 'restore' => 60],
                'medium' => ['name' => '强效法力药水', 'restore' => 120],
                'full' => ['name' => '超级法力药水', 'restore' => 240],
            ],
        ];
        $type = $potionData['sub_type'];
        $level = $potionData['level'];
        if (! isset($potionConfigs[$type][$level])) {
            return null;
        }
        $config = $potionConfigs[$type][$level];
        $statKey = $type === 'hp' ? 'max_hp' : 'max_mana';

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

        $inventoryService = new GameInventoryService;
        if ($character->items()->where('is_in_storage', false)->count() >= $inventoryService::INVENTORY_SIZE) {
            return null;
        }

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

        $potion = GameItem::create([
            'character_id' => $character->id,
            'definition_id' => $definition->id,
            'quality' => 'common',
            'stats' => $definition->base_stats ?? [],
            'affixes' => [],
            'is_in_storage' => false,
            'quantity' => 1,
            'slot_index' => $inventoryService->findEmptySlot($character, false),
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

        $inventoryService = new GameInventoryService;
        if ($character->items()->where('is_in_storage', false)->count() >= $inventoryService::INVENTORY_SIZE) {
            return null;
        }

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
            'slot_index' => $inventoryService->findEmptySlot($character, false),
            'sockets' => 0,
        ]);

        return $gem->load('definition');
    }
}
