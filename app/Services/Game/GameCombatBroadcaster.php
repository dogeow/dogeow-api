<?php

namespace App\Services\Game;

use App\Events\Game\GameCombatUpdate;
use App\Events\Game\GameInventoryUpdate;
use Illuminate\Support\Facades\Log;
use Throwable;

class GameCombatBroadcaster
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function broadcastCombatUpdate(int $characterId, array $payload): void
    {
        $this->dispatchSafely(
            new GameCombatUpdate($characterId, $payload),
            'combat.update',
            $characterId
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function broadcastInventoryUpdate(int $characterId, array $payload): void
    {
        $this->dispatchSafely(
            new GameInventoryUpdate($characterId, $payload),
            'inventory.update',
            $characterId
        );
    }

    private function dispatchSafely(object $event, string $eventName, int $characterId): void
    {
        try {
            event($event);
        } catch (Throwable $e) {
            Log::warning('游戏广播发送失败，已跳过本次推送', [
                'event' => $eventName,
                'character_id' => $characterId,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
