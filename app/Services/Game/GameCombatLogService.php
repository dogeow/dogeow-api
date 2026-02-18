<?php

namespace App\Services\Game;

use App\Models\Game\GameCharacter;
use App\Models\Game\GameCombatLog;
use App\Models\Game\GameMapDefinition;
use App\Models\Game\GameMonsterDefinition;
use Illuminate\Support\Collection;

class GameCombatLogService
{
    /**
     * Create a combat log entry for a round
     */
    public function createRoundLog(
        GameCharacter $character,
        GameMapDefinition $map,
        int $monsterId,
        array $roundResult,
        ?array $potionUsedBeforeRound = null,
        ?array $potionUsedAfterRound = null
    ): GameCombatLog {
        return GameCombatLog::create([
            'character_id' => $character->id,
            'map_id' => $map->id,
            'monster_id' => $monsterId,
            'damage_dealt' => $roundResult['round_damage_dealt'],
            'damage_taken' => $roundResult['round_damage_taken'],
            'victory' => $roundResult['victory'] ?? false,
            'loot_dropped' => ! empty($roundResult['loot']) ? $roundResult['loot'] : null,
            'experience_gained' => $roundResult['experience_gained'] ?? 0,
            'copper_gained' => $roundResult['copper_gained'] ?? 0,
            'duration_seconds' => 0,
            'skills_used' => $roundResult['skills_used_this_round'],
            'potion_used' => [
                'before' => $potionUsedBeforeRound ?: null,
                'after' => $potionUsedAfterRound ?: null,
            ],
        ]);
    }

    /**
     * Create a combat log entry for defeat
     */
    public function createDefeatLog(
        GameCharacter $character,
        GameMapDefinition $map,
        GameMonsterDefinition $monster,
        array $roundResult,
        int $currentRound
    ): GameCombatLog {
        $startTime = $character->combat_started_at ?? now();

        return GameCombatLog::create([
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
    }

    /**
     * Get combat logs for a character
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
     * Get combat statistics for a character
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
     * Format logs for API response
     */
    public function formatLogsForResponse(Collection $logs): array
    {
        return $logs->map(function ($log) {
            return [
                'id' => $log->id,
                'monster' => $log->monster?->name,
                'map' => $log->map?->name,
                'damage_dealt' => $log->damage_dealt,
                'damage_taken' => $log->damage_taken,
                'victory' => $log->victory,
                'experience_gained' => $log->experience_gained,
                'copper_gained' => $log->copper_gained,
                'loot_dropped' => $log->loot_dropped,
                'duration_seconds' => $log->duration_seconds,
                'created_at' => $log->created_at->toISOString(),
            ];
        })->toArray();
    }
}
