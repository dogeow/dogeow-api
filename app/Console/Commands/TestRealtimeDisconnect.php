<?php

namespace App\Console\Commands;

use App\Services\WebSocketDisconnectService;
use Illuminate\Console\Command;

class TestRealtimeDisconnect extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'chat:test-realtime-disconnect {user_id} {--room_id=1}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test real-time WebSocket disconnect detection for a specific user';

    protected $disconnectService;

    public function __construct(WebSocketDisconnectService $disconnectService)
    {
        parent::__construct();
        $this->disconnectService = $disconnectService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $userId = (int) $this->argument('user_id');
        $roomId = (int) $this->option('room_id');

        $this->info("Testing real-time disconnect detection for user {$userId} in room {$roomId}");

        // 检查用户是否在房间中
        $isOnline = $this->disconnectService->isUserOnlineInRoom($userId, $roomId);
        $this->info("User {$userId} is currently " . ($isOnline ? 'online' : 'offline') . " in room {$roomId}");

        if (!$isOnline) {
            $this->warn("User is not online in the room. Cannot test disconnect.");
            return Command::SUCCESS;
        }

        // 获取当前在线人数
        $onlineCount = $this->disconnectService->getRoomOnlineCount($roomId);
        $this->info("Current online count in room {$roomId}: {$onlineCount}");

        // 模拟断开连接
        $this->info("Simulating disconnect for user {$userId}...");
        $this->disconnectService->handleDisconnect($userId);

        // 检查断开后的状态
        $newOnlineCount = $this->disconnectService->getRoomOnlineCount($roomId);
        $this->info("Online count after disconnect: {$newOnlineCount}");

        $isStillOnline = $this->disconnectService->isUserOnlineInRoom($userId, $roomId);
        $this->info("User {$userId} is now " . ($isStillOnline ? 'online' : 'offline') . " in room {$roomId}");

        if (!$isStillOnline && $newOnlineCount === $onlineCount - 1) {
            $this->info("✅ Real-time disconnect detection working correctly!");
        } else {
            $this->error("❌ Real-time disconnect detection failed!");
        }

        return Command::SUCCESS;
    }
}
