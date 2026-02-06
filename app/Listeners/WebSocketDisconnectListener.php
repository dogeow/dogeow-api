<?php

namespace App\Listeners;

use App\Events\Chat\WebSocketDisconnected;
use App\Services\Chat\WebSocketDisconnectService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class WebSocketDisconnectListener implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * WebSocketDisconnectService 实例
     *
     * @var WebSocketDisconnectService
     */
    protected WebSocketDisconnectService $disconnectService;

    public function __construct(WebSocketDisconnectService $disconnectService)
    {
        $this->disconnectService = $disconnectService;
    }

    /**
     * 处理 WebSocket 断开事件
     */
    public function handle(WebSocketDisconnected $event): void
    {
        $user = $event->user;
        $connectionId = $event->connectionId;

        if ($user?->id === null) {
            Log::warning('WebSocket disconnect: No user ID found in event', [
                'event' => $event,
            ]);
            return;
        }

        // 由断开连接服务处理断开逻辑
        $this->disconnectService->handleDisconnect($user->id, $connectionId);
    }
}
