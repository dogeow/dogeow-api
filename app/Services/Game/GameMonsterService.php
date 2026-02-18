<?php

namespace App\Services\Game;

use App\Models\Game\GameCharacter;
use App\Models\Game\GameMapDefinition;
use App\Models\Game\GameMonsterDefinition;

class GameMonsterService
{
    /**
     * 从角色获取现有怪物或生成新怪物
     */
    public function prepareMonsterInfo(GameCharacter $character, GameMapDefinition $map): array
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
            return $this->loadExistingMonsters($character, $existingMonsters);
        }

        return $this->generateNewMonsters($character, $map, $existingMonsters);
    }

    /**
     * 从角色状态加载现有怪物
     */
    public function loadExistingMonsters(GameCharacter $character, array $existingMonsters): array
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
     * 生成新怪物 (1-5个)
     */
    public function generateNewMonsters(GameCharacter $character, GameMapDefinition $map, array $existingMonsters): array
    {
        $monsters = $map->getMonsters();
        if (empty($monsters)) {
            return ['no-monster', null, null, null, null];
        }

        $difficulty = $character->getDifficultyMultipliers();

        // 随机生成 1-5 个怪物
        $monsterCount = rand(1, 5);
        $baseMonster = $monsters[array_rand($monsters)];
        $baseLevel = max(1, $baseMonster->level + rand(-3, 3));

        $monsterDataList = [];
        for ($i = 0; $i < $monsterCount; $i++) {
            $level = $baseLevel + rand(-1, 1);
            $level = max(1, $level);
            $stats = $baseMonster->getCombatStats($level);
            $maxHp = (int) ($stats['hp'] * $difficulty['monster_hp']);

            $monsterDataList[] = [
                'id' => $baseMonster->id,
                'instance_id' => uniqid('m-', true), // 唯一实例ID，用于前端检测新怪物
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

        // 固定5个槽位(0-4)，随机选择N个槽位放怪物，其余为null
        $slotIndices = [0, 1, 2, 3, 4];
        shuffle($slotIndices);
        $newMonsters = array_fill(0, 5, null);
        foreach ($monsterDataList as $idx => $data) {
            $slot = $slotIndices[$idx];
            $data['position'] = $slot;
            $newMonsters[$slot] = $data;
        }

        // 持久化怪物数组（5个槽位，可能包含null）
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

        // 第一个怪物：槽位顺序中第一个存活的怪物
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
     * 尝试添加新怪物（每轮30%概率，最多5个）
     * 如果所有怪物都死亡（has_alive_monster = false），强制刷新100%
     */
    public function tryAddNewMonsters(GameCharacter $character, GameMapDefinition $map, array $roundResult, int $currentRound): array
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

        // 检查本轮所有怪物是否死亡 - 如果是，强制刷新100%
        $allMonstersDead = isset($roundResult['has_alive_monster']) && $roundResult['has_alive_monster'] === false;
        $shouldAddMonster = $allMonstersDead || rand(1, 100) <= 30;

        if (! $shouldAddMonster) {
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
        $baseLevel = max(1, $baseMonster->level + rand(-3, 3));

        foreach ($slotsToFill as $slot) {
            $level = $baseLevel + rand(-1, 1);
            $level = max(1, $level);
            $stats = $baseMonster->getCombatStats($level);
            $maxHp = (int) ($stats['hp'] * $difficulty['monster_hp']);

            $currentMonsters[$slot] = [
                'id' => $baseMonster->id,
                'instance_id' => uniqid('m-', true), // 唯一实例ID，用于前端检测新怪物
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
     * 格式化怪物用于响应（固定5个槽位）
     */
    public function formatMonstersForResponse(GameCharacter $character): array
    {
        $currentMonsters = $character->combat_monsters ?? [];
        $fixedMonsters = array_fill(0, 5, null);
        for ($idx = 0; $idx < 5; $idx++) {
            $m = $currentMonsters[$idx] ?? null;
            if (is_array($m)) {
                $m['position'] = $idx;
                $fixedMonsters[$idx] = $m;
            }
        }

        // 查找第一个存活怪物
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

        return [
            'monsters' => $fixedMonsters,
            'first_alive_monster' => $firstAliveMonster,
        ];
    }
}
