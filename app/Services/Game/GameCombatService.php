<?php

namespace App\Services\Game;

use App\Events\Game\GameCombatUpdate;
use App\Events\Game\GameLootDropped;
use App\Models\Game\GameCharacter;
use App\Models\Game\GameCombatLog;
use App\Models\Game\GameItem;
use App\Models\Game\GameItemDefinition;
use App\Models\Game\GameMapDefinition;
use App\Models\Game\GameMonsterDefinition;

class GameCombatService
{
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
     * 开始战斗
     */
    public function startCombat(GameCharacter $character): array
    {
        if (empty($character->current_map_id)) {
            throw new \InvalidArgumentException('请先选择一个地图');
        }

        if ($character->is_fighting) {
            throw new \InvalidArgumentException('已经在战斗中');
        }

        $character->fill([
            'is_fighting' => true,
            'last_combat_at' => now(),
        ])->save();

        return [
            'is_fighting' => true,
            'message' => '开始自动战斗',
        ];
    }

    /**
     * 停止战斗
     */
    public function stopCombat(GameCharacter $character): array
    {
        $character->clearCombatState();
        $character->update(['is_fighting' => false]);

        return [
            'is_fighting' => false,
            'message' => '停止自动战斗',
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
        if (! $character->is_fighting || ! $character->current_map_id) {
            throw new \InvalidArgumentException('当前不在战斗状态');
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
            throw new \InvalidArgumentException('角色血量不足，已自动停止战斗', [
                'auto_stopped' => true,
                'current_hp' => $currentHp,
            ]);
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
            throw new \InvalidArgumentException('该地图没有怪物，已自动停止战斗，请选择其他地图', [
                'auto_stopped' => true,
            ]);
        }

        // 处理回合
        $currentRound = (int) $character->combat_rounds + 1;
        $skillCooldowns = $character->combat_skill_cooldowns ?? [];
        $skillsUsedAggregated = $character->combat_skills_used ?? [];
        $requestedSkillIds = array_map('intval', array_values($skillIds));

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

        // 获取当前怪物信息用于返回（包含死亡怪物，保持原有顺序）
        $currentMonsters = $character->combat_monsters ?? [];

        // 确保每个怪物有索引位置（用于前端保持位置）
        // 创建固定5个位置的数组，用于保持位置不变
        $fixedMonsters = array_fill(0, 5, null);
        foreach ($currentMonsters as $idx => $m) {
            if ($idx < 5) {
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
        if (!$firstAliveMonster) {
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
            $totalExperience += $m['experience'] ?? 0;
            // 每只怪物掉落 1-10 铜币
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

        // 随机生成 1-3 只新怪物
        $newCount = rand(1, 3);
        $baseMonster = $monsters[array_rand($monsters)];
        $baseLevel = rand(
            max($map->min_level, $baseMonster->level - 3),
            min($map->max_level, $baseMonster->level + 3)
        );

        $newMonsters = [];
        for ($i = 0; $i < $newCount; $i++) {
            $level = $baseLevel + rand(-1, 1);
            $level = max(1, $level);
            $stats = $baseMonster->getCombatStats($level);
            $maxHp = (int) ($stats['hp'] * $difficulty['monster_hp']);

            $newMonsters[] = [
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

        $character->combat_monsters = $newMonsters;
        $roundResult['new_monster_hp'] = array_sum(array_column($newMonsters, 'hp'));
        $roundResult['new_monster_max_hp'] = array_sum(array_column($newMonsters, 'max_hp'));
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

        // 如果已有5只怪物，不再添加
        if (count($currentMonsters) >= 5) {
            $roundResult['new_monster_hp'] = array_sum(array_column($currentMonsters, 'hp'));
            $roundResult['new_monster_max_hp'] = array_sum(array_column($currentMonsters, 'max_hp'));
            return $roundResult;
        }

        // 30% 概率添加新怪物
        if (rand(1, 100) > 30) {
            $roundResult['new_monster_hp'] = array_sum(array_column($currentMonsters, 'hp'));
            $roundResult['new_monster_max_hp'] = array_sum(array_column($currentMonsters, 'max_hp'));
            return $roundResult;
        }

        $difficulty = $character->getDifficultyMultipliers();
        $monsters = $map->getMonsters();

        if (empty($monsters)) {
            return $roundResult;
        }

        // 计算可以添加多少只（最多加到5只）
        $canAdd = 5 - count($currentMonsters);
        $addCount = min($canAdd, rand(1, 2));

        $baseMonster = $monsters[array_rand($monsters)];
        $baseLevel = rand(
            max($map->min_level, $baseMonster->level - 3),
            min($map->max_level, $baseMonster->level + 3)
        );

        for ($i = 0; $i < $addCount; $i++) {
            $level = $baseLevel + rand(-1, 1);
            $level = max(1, $level);
            $stats = $baseMonster->getCombatStats($level);
            $maxHp = (int) ($stats['hp'] * $difficulty['monster_hp']);

            $currentMonsters[] = [
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

        $character->combat_monsters = $currentMonsters;
        $roundResult['new_monster_hp'] = array_sum(array_column($currentMonsters, 'hp'));
        $roundResult['new_monster_max_hp'] = array_sum(array_column($currentMonsters, 'max_hp'));

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
            if (($m['hp'] ?? 0) > 0) {
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

        for ($i = 0; $i < $monsterCount; $i++) {
            $level = $baseLevel + rand(-1, 1);
            $level = max(1, $level);
            $stats = $baseMonster->getCombatStats($level);
            $maxHp = (int) ($stats['hp'] * $difficulty['monster_hp']);

            $newMonsters[] = [
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

        // 持久化怪物数组
        $character->combat_monsters = $newMonsters;
        $character->combat_monster_id = $baseMonster->id;
        $character->combat_monster_level = $baseLevel;
        $character->combat_monster_hp = array_sum(array_column($newMonsters, 'hp'));
        $character->combat_monster_max_hp = array_sum(array_column($newMonsters, 'max_hp'));
        $character->combat_total_damage_dealt = 0;
        $character->combat_total_damage_taken = 0;
        $character->combat_rounds = 0;
        $character->combat_skills_used = null;
        $character->combat_skill_cooldowns = null;
        $character->combat_started_at = now();
        $character->save();

        $firstMonster = $newMonsters[0];
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
     * 处理一回合战斗（支持多怪物）
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
        $charClass = $character->class;

        $monsters = $character->combat_monsters ?? [];
        $difficulty = $character->getDifficultyMultipliers();

        // 查找本回合使用的技能（检查是否是群体攻击技能）
        $activeSkills = $character->skills()
            ->whereHas('skill', fn ($q) => $q->where('type', 'active'))
            ->with('skill')
            ->get();

        $isAoeSkill = false;
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
                    // 按技能定义的 target_type 判断单体/群体，而非职业
                    $isAoeSkill = ($skill->target_type ?? 'single') === 'all';
                    break 2;
                }
                break;
            }
        }

        // 计算角色伤害
        $isCrit = (rand(1, 100) / 100) <= $charCritRate;
        $baseDamage = max(1, $charAttack - ($monsterStats['defense'] ?? 0) * 0.5);
        $damage = $skillDamage > 0
            ? (int) ($baseDamage + $skillDamage)
            : (int) ($baseDamage * ($isCrit ? $charCritDamage : 1));

        // 处理多怪物伤害
        $totalDamageDealt = 0;
        $monstersUpdated = [];
        // 保存回合初血量，用于后面计算「本回合死亡」；foreach 中会通过引用改 $monsters
        $hpAtRoundStart = array_map(fn ($m) => $m['hp'] ?? 0, $monsters);

        // 找出存活的怪物
        $aliveMonsters = array_filter($monsters, fn ($m) => ($m['hp'] ?? 0) > 0);

        // 只有使用群体技能（target_type=all）时才是 AOE；否则单体或普攻只打一只
        $useAoe = $isAoeSkill && ! empty($aliveMonsters);

        // 获取要攻击的怪物列表
        if ($useAoe) {
            // AOE：攻击所有存活怪物
            $targetMonsters = array_values($aliveMonsters);
        } else {
            // 单体：随机选择一只存活怪物
            $targetMonsters = [];
            if (!empty($aliveMonsters)) {
                $aliveValues = array_values($aliveMonsters);
                $randomIndex = array_rand($aliveValues);
                $targetMonsters = [$aliveValues[$randomIndex]];
            }
        }

        foreach ($monsters as &$m) {
            $m['damage_taken'] = 0; // 重置本回合伤害

            if (($m['hp'] ?? 0) <= 0) {
                $monstersUpdated[] = $m;
                continue;
            }

            // 检查这只怪物是否在攻击目标中
            $isTarget = false;
            foreach ($targetMonsters as $tm) {
                if ($tm['id'] === $m['id'] && $tm['level'] === $m['level']) {
                    $isTarget = true;
                    break;
                }
            }

            if (!$isTarget) {
                $monstersUpdated[] = $m;
                continue;
            }

            // 计算伤害
            $targetDamage = $damage;
            if ($useAoe) {
                // AOE攻击，伤害略低
                $targetDamage = (int) ($damage * 0.7);
            }

            $m['hp'] = max(0, ($m['hp'] ?? 0) - $targetDamage);
            $m['damage_taken'] = $targetDamage; // 记录本回合受到的伤害
            $totalDamageDealt += $targetDamage;
            $monstersUpdated[] = $m;
        }
        unset($m);

        // 怪物反击（每只存活怪物都可能攻击）
        $totalMonsterDamage = 0;
        foreach ($monstersUpdated as &$m) {
            if (($m['hp'] ?? 0) <= 0) {
                continue;
            }
            $monsterAttack = $m['attack'] ?? 0;
            $monsterDamage = (int) max(1, $monsterAttack - $charDefense * 0.3);
            $totalMonsterDamage += $monsterDamage;
        }
        unset($m);

        $charHp -= $totalMonsterDamage;

        // 更新怪物数组到数据库
        $character->combat_monsters = $monstersUpdated;
        $newTotalHp = array_sum(array_column($monstersUpdated, 'hp'));

        // 记录技能使用
        foreach ($skillsUsedThisRound as $entry) {
            $id = $entry['skill_id'];
            if (! isset($skillsUsedAggregated[$id])) {
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

        // 检查是否有存活怪物
        $hasAliveMonster = false;
        foreach ($monstersUpdated as $m) {
            if (($m['hp'] ?? 0) > 0) {
                $hasAliveMonster = true;
                break;
            }
        }

        // 本回合死亡的怪物（回合初 hp>0，回合末 hp<=0）发放经验与铜币
        $totalExperience = 0;
        $totalCopper = 0;
        for ($i = 0; $i < count($monstersUpdated); $i++) {
            $before = $hpAtRoundStart[$i] ?? 0;
            $after = $monstersUpdated[$i]['hp'] ?? 0;
            if ($before > 0 && $after <= 0) {
                $totalExperience += $monstersUpdated[$i]['experience'] ?? 0;
                $totalCopper += rand(1, 10);
            }
        }
        $totalExperience = (int) ($totalExperience * $difficulty['reward']);
        $totalCopper = (int) ($totalCopper * $difficulty['reward']);

        return [
            'round_damage_dealt' => $totalDamageDealt,
            'round_damage_taken' => $totalMonsterDamage,
            'new_monster_hp' => $newTotalHp,
            'new_char_hp' => $charHp,
            'new_char_mana' => $currentMana,
            'victory' => false, // 不再以怪物全灭为胜利
            'defeat' => $charHp <= 0,
            'has_alive_monster' => $hasAliveMonster,
            'skills_used_this_round' => $skillsUsedThisRound,
            'new_cooldowns' => $newCooldowns,
            'new_skills_aggregated' => $newSkillsAggregated,
            'monsters_updated' => $monstersUpdated,
            'experience_gained' => $totalExperience,
            'copper_gained' => $totalCopper,
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
     * 处理胜利
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
    ): array {
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
        if (! empty($potionUsed)) {
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
            'loot_dropped' => ! empty($loot) ? $loot : null,
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
            'monster_id' => $monster->id,
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
        if (! empty($loot)) {
            broadcast(new GameLootDropped($character->id, $loot));
        }

        return $result;
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
            'character' => $character->fresh()->toArray(),
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
