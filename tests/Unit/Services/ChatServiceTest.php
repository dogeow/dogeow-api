<?php

namespace Tests\Unit\Services;

use App\Models\Chat\ChatRoom;
use App\Models\Chat\ChatRoomUser;
use App\Models\User;
use App\Services\Chat\ChatCacheService;
use App\Services\Chat\ChatPaginationService;
use App\Services\Chat\ChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ChatService $chatService;

    protected User $user;

    protected ChatRoom $room;

    protected function setUp(): void
    {
        parent::setUp();

        $this->chatService = new ChatService(
            new ChatCacheService,
            new ChatPaginationService
        );

        $this->user = User::factory()->create();
        $this->room = ChatRoom::factory()->create([
            'created_by' => $this->user->id,
        ]);
    }

    public function test_validate_message_with_valid_message()
    {
        $message = 'Hello, this is a valid message!';

        $result = $this->chatService->validateMessage($message);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
        $this->assertEquals($message, $result['sanitized_message']);
    }

    public function test_validate_message_with_empty_message()
    {
        $message = '';

        $result = $this->chatService->validateMessage($message);

        $this->assertFalse($result['valid']);
        $this->assertContains('Message cannot be empty', $result['errors']);
    }

    public function test_validate_message_with_too_long_message()
    {
        $message = str_repeat('a', 1001); // Exceeds MAX_MESSAGE_LENGTH

        $result = $this->chatService->validateMessage($message);

        $this->assertFalse($result['valid']);
        $this->assertContains('Message cannot exceed 1000 characters', $result['errors']);
    }

    public function test_sanitize_message_removes_html_tags()
    {
        $message = "<script>alert('xss')</script>Hello <b>world</b>!";

        $result = $this->chatService->sanitizeMessage($message);

        $this->assertEquals('Hello world!', $result);
    }

    public function test_sanitize_message_normalizes_whitespace()
    {
        $message = "Hello    world\n\n\n!";

        $result = $this->chatService->sanitizeMessage($message);

        $this->assertEquals('Hello world !', $result);
    }

    public function test_process_mentions_with_mentions()
    {
        // Create users first
        $user1 = User::factory()->create(['name' => 'john']);
        $user2 = User::factory()->create(['name' => 'jane']);

        $message = 'Hello @john and @jane, how are you?';

        $result = $this->chatService->processMentions($message);

        $this->assertCount(2, $result);
        $this->assertEquals($user1->id, $result[0]['user_id']);
        $this->assertEquals($user2->id, $result[1]['user_id']);
    }

    public function test_process_mentions_without_mentions()
    {
        $message = 'Hello world!';

        $result = $this->chatService->processMentions($message);

        $this->assertEmpty($result);
    }

    public function test_format_message_with_mentions()
    {
        $message = 'Hello @john!';
        $mentions = [
            [
                'user_id' => 1,
                'username' => 'john',
            ],
        ];

        $result = $this->chatService->formatMessage($message, $mentions);

        $this->assertStringContainsString('@john', $result);
    }

    public function test_create_room_with_valid_data()
    {
        $roomData = [
            'name' => 'Test Room',
            'description' => 'A test room',
        ];

        $result = $this->chatService->createRoom($roomData, $this->user->id);

        $this->assertTrue($result['success']);
        $this->assertDatabaseHas('chat_rooms', [
            'name' => 'Test Room',
            'description' => 'A test room',
            'created_by' => $this->user->id,
        ]);
    }

    public function test_create_room_with_invalid_data()
    {
        $roomData = [
            'name' => '', // Invalid: empty name
            'description' => 'A test room',
        ];

        $result = $this->chatService->createRoom($roomData, $this->user->id);

        $this->assertFalse($result['success']);
        $this->assertNotEmpty($result['errors']);
    }

    public function test_validate_room_data_with_valid_data()
    {
        $roomData = [
            'name' => 'Test Room',
            'description' => 'A test room',
        ];

        $result = $this->chatService->validateRoomData($roomData);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    public function test_validate_room_data_with_invalid_name()
    {
        $roomData = [
            'name' => 'ab', // Too short
            'description' => 'A test room',
        ];

        $result = $this->chatService->validateRoomData($roomData);

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
    }

    public function test_check_room_permission_for_creator()
    {
        $result = $this->chatService->checkRoomPermission($this->room->id, $this->user->id, 'delete');

        $this->assertTrue($result);
    }

    public function test_check_room_permission_for_non_creator()
    {
        $otherUser = User::factory()->create();

        $result = $this->chatService->checkRoomPermission($this->room->id, $otherUser->id, 'delete');

        $this->assertFalse($result);
    }

    public function test_join_room_successfully()
    {
        $result = $this->chatService->joinRoom($this->room->id, $this->user->id);

        $this->assertTrue($result['success']);
        $this->assertDatabaseHas('chat_room_users', [
            'room_id' => $this->room->id,
            'user_id' => $this->user->id,
            'is_online' => true,
        ]);
    }

    public function test_join_room_when_already_member()
    {
        // First join
        $this->chatService->joinRoom($this->room->id, $this->user->id);

        // Try to join again
        $result = $this->chatService->joinRoom($this->room->id, $this->user->id);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('already a member', $result['message']);
    }

    public function test_leave_room_successfully()
    {
        // First join
        $this->chatService->joinRoom($this->room->id, $this->user->id);

        // Then leave
        $result = $this->chatService->leaveRoom($this->room->id, $this->user->id);

        $this->assertTrue($result['success']);
        $this->assertDatabaseMissing('chat_room_users', [
            'room_id' => $this->room->id,
            'user_id' => $this->user->id,
        ]);
    }

    public function test_leave_room_when_not_member()
    {
        $result = $this->chatService->leaveRoom($this->room->id, $this->user->id);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('not a member', $result['message']);
    }

    public function test_update_user_status()
    {
        // First join
        $this->chatService->joinRoom($this->room->id, $this->user->id);

        // Update status to offline
        $result = $this->chatService->updateUserStatus($this->room->id, $this->user->id, false);

        $this->assertTrue($result['success']);
        $this->assertDatabaseHas('chat_room_users', [
            'room_id' => $this->room->id,
            'user_id' => $this->user->id,
            'is_online' => false,
        ]);
    }

    public function test_get_online_users()
    {
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();

        // Join users to room
        $this->chatService->joinRoom($this->room->id, $this->user->id);
        $this->chatService->joinRoom($this->room->id, $user2->id);
        $this->chatService->joinRoom($this->room->id, $user3->id);

        // Set one user offline
        $this->chatService->updateUserStatus($this->room->id, $user3->id, false);

        $onlineUsers = $this->chatService->getOnlineUsers($this->room->id);

        $this->assertCount(2, $onlineUsers);
        $this->assertTrue($onlineUsers->contains($this->user));
        $this->assertTrue($onlineUsers->contains($user2));
        $this->assertFalse($onlineUsers->contains($user3));
    }

    public function test_process_heartbeat()
    {
        // First join
        $this->chatService->joinRoom($this->room->id, $this->user->id);

        $result = $this->chatService->processHeartbeat($this->room->id, $this->user->id);

        $this->assertTrue($result['success']);
        $this->assertDatabaseHas('chat_room_users', [
            'room_id' => $this->room->id,
            'user_id' => $this->user->id,
            'is_online' => true,
        ]);
    }

    public function test_cleanup_inactive_users()
    {
        $user2 = User::factory()->create();

        // Join users to room
        $this->chatService->joinRoom($this->room->id, $this->user->id);
        $this->chatService->joinRoom($this->room->id, $user2->id);

        // Set one user as inactive (last_seen_at more than 5 minutes ago)
        ChatRoomUser::where('user_id', $user2->id)->update([
            'last_seen_at' => now()->subMinutes(10),
        ]);

        $result = $this->chatService->cleanupInactiveUsers();

        $this->assertTrue($result['success']);
        $this->assertGreaterThan(0, $result['cleaned_count']);
    }

    public function test_get_active_rooms()
    {
        $activeRoom = ChatRoom::factory()->create(['is_active' => true]);
        $inactiveRoom = ChatRoom::factory()->create(['is_active' => false]);

        $activeRooms = $this->chatService->getActiveRooms();

        $this->assertTrue($activeRooms->contains($activeRoom));
        $this->assertFalse($activeRooms->contains($inactiveRoom));
    }
}
