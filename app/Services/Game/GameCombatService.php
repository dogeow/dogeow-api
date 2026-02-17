<?php

namespace App\Services\Game;

use App\Events\Game\GameCombatUpdate;
use App\Events\Game\GameInventoryUpdate;
use App\Models\Game\GameCharacter;
use App\Models\Game\GameMapDefinition;
use App\Models\Game\GameMonsterDefinition;
use Illuminate\Support\Facades\Log;

class GameCombatService
{
    public function __construct(
        private readonly CombatRoundProcessor $roundProcessor,
        private readonly GameMonsterService $monsterService,
        private readonly GamePotionService $potionService,
        private readonly GameCombatLootService $lootService,
        private readonly GameCombatLogService $combatLogService
    ) {}

    /**
     * Get combat status
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
     * Update potion settings
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
     * Execute one round of combat (supports multi-monster continuous combat)
     */
    public function executeRound(GameCharacter $character, array $skillIds = []): array
    {
        if (! $character->current_map_id) {
            throw new \InvalidArgumentException('请先选择一个地图');
        }

        // If not in combat, automatically start combat
        if (! $character->is_fighting) {
            $character->fill([
                'is_fighting' => true,
                'last_combat_at' => now(),
            ])->save();
        }

        $character->initializeHpMana();
        $currentHp = $character->getCurrentHp();
        $currentMana = $character->getCurrentMana();

        // Auto-use potions before round
        $charStats = $character->getCombatStats();
        $potionUsedBeforeRound = $this->potionService->tryAutoUsePotions($character, $currentHp, $currentMana, $charStats);
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

        // Prepare monster info
        [
            $monster,
            $monsterLevel,
            $monsterStats,
            $monsterHp,
            $monsterMaxHp
        ] = $this->monsterService->prepareMonsterInfo($character, $map);

        if (! $monster) {
            throw new \InvalidArgumentException('当前战斗怪物不存在，已清除状态');
        }
        if ($monster === 'no-monster') {
            $character->update(['is_fighting' => false]);
            throw new \RuntimeException('该地图没有怪物，已自动停止战斗，请选择其他地图', previous: new \Exception(json_encode([
                'auto_stopped' => true,
            ])));
        }

        // Process round
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

        // Auto-use potions after round
        $charStats = $character->getCombatStats();
        $potionUsed = $this->potionService->tryAutoUsePotions($character, $roundResult['new_char_hp'], $roundResult['new_char_mana'], $charStats);
        if (! empty($potionUsed)) {
            $roundResult['new_char_hp'] = $character->getCurrentHp();
            $roundResult['new_char_mana'] = $character->getCurrentMana();
        }

        // Persist combat state
        $this->persistCombatState($character, $roundResult, $currentRound);

        // Handle defeat
        if ($roundResult['defeat']) {
            return $this->handleDefeat($character, $map, $monster, $monsterLevel, $monsterMaxHp, $currentRound, $roundResult, $monsterHp);
        }

        // Check if all monsters are dead
        $isVictory = ! ($roundResult['has_alive_monster'] ?? true);
        if ($isVictory) {
            // All monsters dead, don't respawn immediately, keep dead monsters visible until next round
            $roundResult['new_monster_max_hp'] = $roundResult['new_monster_hp']; // Keep total HP unchanged
            $roundResult['victory'] = true;
        }

        // Always try to add new monsters when some monsters die (even if not all dead)
        // This enables continuous combat flow: monsters respawn as they die, not waiting for all to die
        $roundResult = $this->monsterService->tryAddNewMonsters($character, $map, $roundResult, $currentRound);

        // Grant exp and copper for dead monsters this round
        $expGained = $roundResult['experience_gained'] ?? 0;
        $copperGained = $roundResult['copper_gained'] ?? 0;
        if ($expGained > 0 || $copperGained > 0) {
            $rewards = $this->lootService->distributeRewards($character, $roundResult);
            $roundResult['loot'] = array_merge($roundResult['loot'] ?? [], ['copper' => $copperGained]);
        }

        // Process death loot
        $deathLoot = $this->lootService->processDeathLoot($character, $roundResult);
        if (! empty($deathLoot)) {
            $roundResult['loot'] = array_merge($roundResult['loot'] ?? [], $deathLoot);
        }

        // Save updated monster state
        $character->combat_monster_hp = max(0, $roundResult['new_monster_hp']);
        $character->combat_monster_max_hp = max(0, $roundResult['new_monster_max_hp'] ?? $roundResult['new_monster_hp']);
        $character->save();

        // Get current monsters for response (fixed 5 slots)
        $monsterData = $this->monsterService->formatMonstersForResponse($character);
        $fixedMonsters = $monsterData['monsters'];
        $firstAliveMonster = $monsterData['first_alive_monster'];

        // Create combat log
        $combatLog = $this->combatLogService->createRoundLog(
            $character,
            $map,
            $firstAliveMonster['id'] ?? $monster->id,
            $roundResult,
            $currentRound,
            $potionUsedBeforeRound,
            $potionUsed
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
            'potion_used' => [
                'before' => $potionUsedBeforeRound ?? [],
                'after' => $potionUsed ?? [],
            ],
            'skills_used' => $roundResult['skills_used_this_round'],
            'skill_target_positions' => $roundResult['skill_target_positions'] ?? [],
            'character' => $character->fresh()->toArray(),
            'combat_log_id' => $combatLog->id,
        ];

        broadcast(new GameCombatUpdate($character->id, $result));
        $character->refresh();
        $inventoryPayload = app(GameInventoryService::class)->getInventoryForBroadcast($character);
        Log::info('Game inventory broadcast payload', [
            'character_id' => $character->id,
            'inventory_count' => count($inventoryPayload['inventory'] ?? []),
            'first_items_quantity' => array_map(fn ($i) => ['id' => $i['id'] ?? null, 'quantity' => $i['quantity'] ?? null], array_slice($inventoryPayload['inventory'] ?? [], 0, 3)),
        ]);
        broadcast(new GameInventoryUpdate($character->id, $inventoryPayload));

        return $result;
    }

    /**
     * Persist combat state
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

        // Save updated monster array
        if (isset($roundResult['monsters_updated'])) {
            $character->combat_monsters = $roundResult['monsters_updated'];
        }
    }

    /**
     * Handle defeat
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
        // On defeat
        $character->current_hp = max(0, $roundResult['new_char_hp']);
        $character->is_fighting = false;

        $combatLog = $this->combatLogService->createDefeatLog($character, $map, $monster, $roundResult, $currentRound);

        $character->clearCombatState();
        $character->save();

        // Debug: check values after save
        Log::info('[handleDefeat] after save, current_hp in DB:', [
            'character_id' => $character->id,
            'current_hp' => $character->current_hp,
            'fresh_current_hp' => $character->fresh()->current_hp,
        ]);

        $freshCharacter = $character->fresh();
        Log::info('[handleDefeat] fresh character:', ['current_hp' => $freshCharacter->current_hp]);
        $charArray = $freshCharacter->toArray();
        $charArray['current_hp'] = 0;
        $charArray['current_mana'] = 0;
        Log::info('[handleDefeat] charArray after override:', ['current_hp' => $charArray['current_hp']]);

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
        $character->refresh();
        $inventoryPayload = app(GameInventoryService::class)->getInventoryForBroadcast($character);
        Log::info('Game inventory broadcast payload (handleDefeat)', [
            'character_id' => $character->id,
            'first_items_quantity' => array_map(fn ($i) => ['id' => $i['id'] ?? null, 'quantity' => $i['quantity'] ?? null], array_slice($inventoryPayload['inventory'] ?? [], 0, 3)),
        ]);
        broadcast(new GameInventoryUpdate($character->id, $inventoryPayload));

        return $result;
    }

    /**
     * Get combat logs
     */
    public function getCombatLogs(GameCharacter $character): array
    {
        return $this->combatLogService->getCombatLogs($character);
    }

    /**
     * Get combat statistics
     */
    public function getCombatStats(GameCharacter $character): array
    {
        return $this->combatLogService->getCombatStats($character);
    }
}
