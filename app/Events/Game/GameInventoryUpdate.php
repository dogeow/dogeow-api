<?php

namespace App\Events\Game;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * 背包/仓库/装备更新事件，通过队列推送到 Reverb，前端直接使用 payload 更新状态，无需再请求 GET /rpg/inventory。
 */
class GameInventoryUpdate implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array  $payload  与 GET /rpg/inventory 一致的数组：inventory, storage, equipment, inventory_size, storage_size
     */
    public function __construct(
        public int $characterId,
        public array $payload
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel("game.{$this->characterId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'inventory.update';
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
