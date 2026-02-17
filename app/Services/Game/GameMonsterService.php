<?php

namespace App\Services\Game;

use App\Models\Game\GameCharacter;
use App\Models\Game\GameMapDefinition;
use App\Models\Game\GameMonsterDefinition;

class GameMonsterService
{
    /**
     * Get existing monsters from character or generate new ones
     */
    public function prepareMonsterInfo(GameCharacter $character, GameMapDefinition $map): array
    {
        $existingMonsters = $character->combat_monsters ?? [];

        // Check if there's any alive monster
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
     * Load existing monsters from character state
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
     * Generate new monsters (1-5)
     */
    public function generateNewMonsters(GameCharacter $character, GameMapDefinition $map, array $existingMonsters): array
    {
        $monsters = $map->getMonsters();
        if (empty($monsters)) {
            return ['no-monster', null, null, null, null];
        }

        $difficulty = $character->getDifficultyMultipliers();

        // Generate 1-5 monsters randomly
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

        // Fixed 5 slots (0-4), randomly select N slots for monsters, rest are null
        $slotIndices = [0, 1, 2, 3, 4];
        shuffle($slotIndices);
        $newMonsters = array_fill(0, 5, null);
        foreach ($monsterDataList as $idx => $data) {
            $slot = $slotIndices[$idx];
            $data['position'] = $slot;
            $newMonsters[$slot] = $data;
        }

        // Persist monster array (5 slots, may contain null)
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

        // First monster: first alive monster in slot order
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
     * Try to add new monsters (30% chance per round, max 5)
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
     * Respawn monsters (when all monsters are defeated) and distribute rewards
     */
    public function respawnMonsters(GameCharacter $character, GameMapDefinition $map, array $roundResult, int $currentRound): array
    {
        $difficulty = $character->getDifficultyMultipliers();
        $monsters = $map->getMonsters();

        if (empty($monsters)) {
            return $roundResult;
        }

        // Get monsters that died in the previous round, calculate exp and loot
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

        // Apply difficulty multipliers
        $totalExperience = (int) ($totalExperience * $difficulty['reward']);
        $totalCopper = (int) ($totalCopper * $difficulty['reward']);

        // Grant experience
        if ($totalExperience > 0) {
            $character->addExperience($totalExperience);
        }

        // Grant copper
        if ($totalCopper > 0) {
            $character->copper += $totalCopper;
            $loot['copper'] = $totalCopper;
        }

        // Random loot drop (low chance)
        if (! empty($monsters)) {
            $baseMonster = $monsters[array_rand($monsters)];
            $lootResult = $baseMonster->generateLoot($character->level);
            if (isset($lootResult['item'])) {
                $item = app(GameCombatLootService::class)->createItem($character, $lootResult['item']);
                if ($item) {
                    $loot['item'] = $item;
                }
            }
            if (isset($lootResult['potion'])) {
                $potion = app(GameCombatLootService::class)->createPotion($character, $lootResult['potion']);
                if ($potion) {
                    $loot['potion'] = $potion;
                }
            }
        }

        // Randomly generate 1-3 new monsters, place in random slots (fixed 5 slots)
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
     * Format monsters for response (fixed 5 slots)
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

        // Find first alive monster
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
