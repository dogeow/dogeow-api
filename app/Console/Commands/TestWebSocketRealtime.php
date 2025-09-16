<?php

namespace App\Console\Commands;

use App\Events\WebSocketDisconnected;
use App\Models\User;
use App\Services\WebSocketDisconnectService;
use Illuminate\Console\Command;

class TestWebSocketRealtime extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'chat:test-realtime {user_id} {--room_id=1} {--simulate-disconnect}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test real-time WebSocket disconnect detection and cleanup';

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
        $simulateDisconnect = $this->option('simulate-disconnect');

        $this->info("🧪 Testing real-time WebSocket disconnect detection");
        $this->info("User ID: {$userId}");
        $this->info("Room ID: {$roomId}");
        $this->newLine();

        // 检查用户是否存在
        $user = User::find($userId);
        if (!$user) {
            $this->error("❌ User with ID {$userId} not found");
            return Command::FAILURE;
        }

        $this->info("✅ User found: {$user->name}");

        // 检查用户当前状态
        $isOnline = $this->disconnectService->isUserOnlineInRoom($userId, $roomId);
        $onlineCount = $this->disconnectService->getRoomOnlineCount($roomId);

        $this->info("Current status:");
        $this->info("- User is " . ($isOnline ? 'online' : 'offline') . " in room {$roomId}");
        $this->info("- Room {$roomId} has {$onlineCount} online users");

        if ($simulateDisconnect) {
            $this->newLine();
            $this->info("🔄 Simulating WebSocket disconnect...");
            
            // 模拟断开连接事件
            event(new WebSocketDisconnected($user, 'test-connection-id'));
            
            // 等待一下让事件处理完成
            sleep(1);
            
            // 检查断开后的状态
            $newIsOnline = $this->disconnectService->isUserOnlineInRoom($userId, $roomId);
            $newOnlineCount = $this->disconnectService->getRoomOnlineCount($roomId);
            
            $this->info("After disconnect:");
            $this->info("- User is " . ($newIsOnline ? 'online' : 'offline') . " in room {$roomId}");
            $this->info("- Room {$roomId} has {$newOnlineCount} online users");
            
            if (!$newIsOnline && $newOnlineCount === $onlineCount - 1) {
                $this->info("✅ Real-time disconnect detection working correctly!");
            } else {
                $this->error("❌ Real-time disconnect detection failed!");
                return Command::FAILURE;
            }
        } else {
            $this->newLine();
            $this->info("💡 Use --simulate-disconnect flag to test the disconnect functionality");
        }

        return Command::SUCCESS;
    }
}