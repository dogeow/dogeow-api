<?php

namespace Tests\Unit\Services\Game;

use App\Events\Game\GameCombatUpdate;
use App\Events\Game\GameInventoryUpdate;
use App\Services\Game\GameCombatBroadcaster;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class GameCombatBroadcasterTest extends TestCase
{
    public function test_broadcast_combat_update_dispatches_event(): void
    {
        Event::fake([GameCombatUpdate::class]);

        $broadcaster = new GameCombatBroadcaster;
        $broadcaster->broadcastCombatUpdate(42, [
            'victory' => true,
            'rounds' => 3,
        ]);

        Event::assertDispatched(GameCombatUpdate::class, function (GameCombatUpdate $event): bool {
            return $event->characterId === 42
                && $event->combatResult['victory'] === true
                && $event->combatResult['rounds'] === 3;
        });
    }

    public function test_broadcast_inventory_update_dispatches_event(): void
    {
        Event::fake([GameInventoryUpdate::class]);

        $broadcaster = new GameCombatBroadcaster;
        $broadcaster->broadcastInventoryUpdate(7, [
            'inventory' => [],
            'storage' => [],
        ]);

        Event::assertDispatched(GameInventoryUpdate::class, function (GameInventoryUpdate $event): bool {
            return $event->characterId === 7
                && $event->payload['inventory'] === []
                && $event->payload['storage'] === [];
        });
    }

    public function test_broadcast_swallows_dispatch_exception_and_logs_warning(): void
    {
        $dispatcher = Mockery::mock(Dispatcher::class);
        $dispatcher->shouldReceive('dispatch')
            ->once()
            ->withArgs(fn (object $event): bool => $event instanceof GameCombatUpdate)
            ->andThrow(new RuntimeException('broadcast failed'));

        $this->app->instance('events', $dispatcher);
        $this->app->instance(Dispatcher::class, $dispatcher);

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return $message === '游戏广播发送失败，已跳过本次推送'
                    && $context['event'] === 'combat.update'
                    && $context['character_id'] === 99
                    && $context['exception'] === RuntimeException::class
                    && $context['message'] === 'broadcast failed';
            });

        $broadcaster = new GameCombatBroadcaster;
        $broadcaster->broadcastCombatUpdate(99, ['victory' => false]);

        $this->assertTrue(true);
    }
}
