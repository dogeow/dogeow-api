<?php

namespace App\Listeners;

use App\Events\WebSocketDisconnected;
use App\Services\WebSocketDisconnectService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class WebSocketDisconnectListener implements ShouldQueue
{
    use InteractsWithQueue;

    protected $disconnectService;

    public function __construct(WebSocketDisconnectService $disconnectService)
    {
        $this->disconnectService = $disconnectService;
    }

    /**
     * Handle the event.
     */
    public function handle(WebSocketDisconnected $event): void
    {
        $userId = $event->user->id;
        $connectionId = $event->connectionId;
        
        if (!$userId) {
            Log::warning('WebSocket disconnect: No user ID found in event');
            return;
        }

        // 使用专门的断开连接服务处理
        $this->disconnectService->handleDisconnect($userId, $connectionId);
    }
}
