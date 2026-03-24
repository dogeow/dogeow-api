<?php

namespace Tests\Unit\Services\Chat;

use App\Http\Controllers\Api\Chat\ChatPresenceController;
use App\Models\Chat\ChatRoom;
use App\Models\Chat\ChatRoomUser;
use App\Models\User;
use App\Services\Chat\ChatCacheService;
use App\Services\Chat\ChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test stubs for ChatPresenceController
 *
 * @group chat
 * @group stubs
 */
class ChatPresenceControllerTest extends TestCase
{
    use RefreshDatabase;

    protected ChatPresenceController $controller;
    protected ChatService $chatService;
    protected ChatCacheService $cacheService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->chatService = new ChatService;
        $this->cacheService = new ChatCacheService;
        $this->controller = new ChatPresenceController($this->chatService, $this->cacheService);
    }

    /**
     * @test
     */
    public function users_returns_online_users_for_room(): void
    {
        $this->markTestIncomplete('Test stub - implementation needed');
    }

    /**
     * @test
     */
    public function users_rejects_user_not_in_room(): void
    {
        $this->markTestIncomplete('Test stub - implementation needed');
    }

    /**
     * @test
     */
    public function heartbeat_updates_user_last_seen(): void
    {
        $this->markTestIncomplete('Test stub - implementation needed');
    }

    /**
     * @test
     */
    public function heartbeat_requires_user_to_be_in_room(): void
    {
        $this->markTestIncomplete('Test stub - implementation needed');
    }
}
