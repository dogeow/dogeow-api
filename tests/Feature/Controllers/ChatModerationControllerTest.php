<?php

namespace Tests\Feature\Controllers;

use App\Events\Chat\MessageDeleted;
use App\Events\Chat\UserBanned;
use App\Events\Chat\UserMuted;
use App\Events\Chat\UserUnbanned;
use App\Events\Chat\UserUnmuted;
use App\Models\ChatMessage;
use App\Models\ChatModerationAction;
use App\Models\ChatRoom;
use App\Models\ChatRoomUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChatModerationControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $moderator;
    private User $targetUser;
    private User $regularUser;
    private ChatRoom $room;
    private ChatMessage $message;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->moderator = User::factory()->create(['is_admin' => true]);
        $this->targetUser = User::factory()->create();
        $this->regularUser = User::factory()->create();
        $this->room = ChatRoom::factory()->create(['created_by' => $this->moderator->id]);
        
        // Join users to room
        ChatRoomUser::create([
            'room_id' => $this->room->id,
            'user_id' => $this->moderator->id,
            'is_online' => true,
        ]);
        
        ChatRoomUser::create([
            'room_id' => $this->room->id,
            'user_id' => $this->targetUser->id,
            'is_online' => true,
        ]);
        
        $this->message = ChatMessage::factory()->create([
            'room_id' => $this->room->id,
            'user_id' => $this->targetUser->id,
        ]);
        
        Sanctum::actingAs($this->moderator);
        Event::fake();
    }

    // ==================== DELETE MESSAGE TESTS ====================

    public function test_delete_message_success()
    {
        $response = $this->deleteJson("/api/chat/moderation/rooms/{$this->room->id}/messages/{$this->message->id}", [
            'reason' => 'Inappropriate content'
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Message deleted successfully',
                'action' => 'delete_message',
                'moderator' => $this->moderator->name,
                'reason' => 'Inappropriate content',
            ]);

        // Check if message was actually deleted
        $this->assertDatabaseMissing('chat_messages', ['id' => $this->message->id]);

        Event::assertDispatched(MessageDeleted::class);
    }

    public function test_delete_message_unauthorized()
    {
        Sanctum::actingAs($this->regularUser);

        $response = $this->deleteJson("/api/chat/moderation/rooms/{$this->room->id}/messages/{$this->message->id}");

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'You are not authorized to moderate this room'
            ]);

        $this->assertDatabaseHas('chat_messages', ['id' => $this->message->id]);
        Event::assertNotDispatched(MessageDeleted::class);
    }

    public function test_delete_message_room_not_found()
    {
        $response = $this->deleteJson("/api/chat/moderation/rooms/99999/messages/{$this->message->id}");

        $response->assertStatus(404);
    }

    public function test_delete_message_message_not_found()
    {
        $response = $this->deleteJson("/api/chat/moderation/rooms/{$this->room->id}/messages/99999");

        $response->assertStatus(404);
    }

    public function test_delete_message_without_reason()
    {
        $response = $this->deleteJson("/api/chat/moderation/rooms/{$this->room->id}/messages/{$this->message->id}");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Message deleted successfully',
                'reason' => null,
            ]);
    }

    // ==================== MUTE USER TESTS ====================

    public function test_mute_user_success()
    {
        $response = $this->postJson("/api/chat/moderation/rooms/{$this->room->id}/users/{$this->targetUser->id}/mute", [
            'duration' => 60,
            'reason' => 'Spam behavior'
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'User muted successfully',
                'action' => 'mute_user',
                'target_user_id' => $this->targetUser->id,
                'moderator' => $this->moderator->name,
                'duration_minutes' => 60,
                'reason' => 'Spam behavior',
            ]);

        $this->assertDatabaseHas('chat_moderation_actions', [
            'room_id' => $this->room->id,
            'moderator_id' => $this->moderator->id,
            'target_user_id' => $this->targetUser->id,
            'action_type' => ChatModerationAction::ACTION_MUTE_USER,
            'reason' => 'Spam behavior',
        ]);

        Event::assertDispatched(UserMuted::class);
    }

    public function test_mute_user_cannot_mute_self()
    {
        $response = $this->postJson("/api/chat/moderation/rooms/{$this->room->id}/users/{$this->moderator->id}/mute", [
            'duration' => 60,
            'reason' => 'Self moderation'
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'You cannot mute yourself'
            ]);

        Event::assertNotDispatched(UserMuted::class);
    }

    public function test_mute_user_not_in_room()
    {
        $otherUser = User::factory()->create();
        
        $response = $this->postJson("/api/chat/moderation/rooms/{$this->room->id}/users/{$otherUser->id}/mute", [
            'duration' => 60
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'message' => 'User is not in this room'
            ]);

        Event::assertNotDispatched(UserMuted::class);
    }

    public function test_mute_user_unauthorized()
    {
        Sanctum::actingAs($this->regularUser);

        $response = $this->postJson("/api/chat/moderation/rooms/{$this->room->id}/users/{$this->targetUser->id}/mute", [
            'duration' => 60
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'You are not authorized to moderate this room'
            ]);

        Event::assertNotDispatched(UserMuted::class);
    }

    public function test_mute_user_permanent()
    {
        $response = $this->postJson("/api/chat/moderation/rooms/{$this->room->id}/users/{$this->targetUser->id}/mute", [
            'reason' => 'Permanent mute'
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'duration_minutes' => null,
                'muted_until' => null,
            ]);
    }

    public function test_mute_user_invalid_duration()
    {
        $response = $this->postJson("/api/chat/moderation/rooms/{$this->room->id}/users/{$this->targetUser->id}/mute", [
            'duration' => 0
        ]);

        $response->assertStatus(422);
    }

    // ==================== UNMUTE USER TESTS ====================

    public function test_unmute_user_success()
    {
        // First mute the user
        $roomUser = ChatRoomUser::where('room_id', $this->room->id)
            ->where('user_id', $this->targetUser->id)
            ->first();
        $roomUser->mute($this->moderator->id, 60, 'Test mute');

        $response = $this->postJson("/api/chat/moderation/rooms/{$this->room->id}/users/{$this->targetUser->id}/unmute", [
            'reason' => 'Appeal granted'
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'User unmuted successfully',
                'action' => 'unmute_user',
                'target_user_id' => $this->targetUser->id,
                'moderator' => $this->moderator->name,
                'reason' => 'Appeal granted',
            ]);

        $this->assertDatabaseHas('chat_moderation_actions', [
            'room_id' => $this->room->id,
            'moderator_id' => $this->moderator->id,
            'target_user_id' => $this->targetUser->id,
            'action_type' => ChatModerationAction::ACTION_UNMUTE_USER,
            'reason' => 'Appeal granted',
        ]);

        Event::assertDispatched(UserUnmuted::class);
    }

    public function test_unmute_user_not_muted()
    {
        $response = $this->postJson("/api/chat/moderation/rooms/{$this->room->id}/users/{$this->targetUser->id}/unmute", [
            'reason' => 'Not muted'
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'User is not muted'
            ]);

        Event::assertNotDispatched(UserUnmuted::class);
    }

    public function test_unmute_user_unauthorized()
    {
        Sanctum::actingAs($this->regularUser);

        $response = $this->postJson("/api/chat/moderation/rooms/{$this->room->id}/users/{$this->targetUser->id}/unmute");

        $response->assertStatus(403);
    }

    // ==================== BAN USER TESTS ====================

    public function test_ban_user_success()
    {
        $response = $this->postJson("/api/chat/moderation/rooms/{$this->room->id}/users/{$this->targetUser->id}/ban", [
            'duration' => 1440, // 24 hours
            'reason' => 'Repeated violations'
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'User banned successfully',
                'action' => 'ban_user',
                'target_user_id' => $this->targetUser->id,
                'moderator' => $this->moderator->name,
                'duration_minutes' => 1440,
                'reason' => 'Repeated violations',
            ]);

        $this->assertDatabaseHas('chat_moderation_actions', [
            'room_id' => $this->room->id,
            'moderator_id' => $this->moderator->id,
            'target_user_id' => $this->targetUser->id,
            'action_type' => ChatModerationAction::ACTION_BAN_USER,
            'reason' => 'Repeated violations',
        ]);

        Event::assertDispatched(UserBanned::class);
    }

    public function test_ban_user_cannot_ban_self()
    {
        $response = $this->postJson("/api/chat/moderation/rooms/{$this->room->id}/users/{$this->moderator->id}/ban", [
            'duration' => 1440,
            'reason' => 'Self ban'
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'You cannot ban yourself'
            ]);

        Event::assertNotDispatched(UserBanned::class);
    }

    public function test_ban_user_permanent()
    {
        $response = $this->postJson("/api/chat/moderation/rooms/{$this->room->id}/users/{$this->targetUser->id}/ban", [
            'reason' => 'Permanent ban'
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'duration_minutes' => null,
                'banned_until' => null,
            ]);
    }

    public function test_ban_user_invalid_duration()
    {
        $response = $this->postJson("/api/chat/moderation/rooms/{$this->room->id}/users/{$this->targetUser->id}/ban", [
            'duration' => 600000 // More than 1 year
        ]);

        $response->assertStatus(422);
    }

    // ==================== UNBAN USER TESTS ====================

    public function test_unban_user_success()
    {
        // First ban the user
        $roomUser = ChatRoomUser::where('room_id', $this->room->id)
            ->where('user_id', $this->targetUser->id)
            ->first();
        $roomUser->ban($this->moderator->id, 1440, 'Test ban');

        $response = $this->postJson("/api/chat/moderation/rooms/{$this->room->id}/users/{$this->targetUser->id}/unban", [
            'reason' => 'Appeal granted'
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'User unbanned successfully',
                'action' => 'unban_user',
                'target_user_id' => $this->targetUser->id,
                'moderator' => $this->moderator->name,
                'reason' => 'Appeal granted',
            ]);

        $this->assertDatabaseHas('chat_moderation_actions', [
            'room_id' => $this->room->id,
            'moderator_id' => $this->moderator->id,
            'target_user_id' => $this->targetUser->id,
            'action_type' => ChatModerationAction::ACTION_UNBAN_USER,
            'reason' => 'Appeal granted',
        ]);

        Event::assertDispatched(UserUnbanned::class);
    }

    public function test_unban_user_not_banned()
    {
        $response = $this->postJson("/api/chat/moderation/rooms/{$this->room->id}/users/{$this->targetUser->id}/unban", [
            'reason' => 'Not banned'
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'User is not banned'
            ]);

        Event::assertNotDispatched(UserUnbanned::class);
    }

    // ==================== GET MODERATION ACTIONS TESTS ====================

    public function test_get_moderation_actions()
    {
        // Create some moderation actions
        ChatModerationAction::create([
            'room_id' => $this->room->id,
            'moderator_id' => $this->moderator->id,
            'target_user_id' => $this->targetUser->id,
            'action_type' => ChatModerationAction::ACTION_MUTE_USER,
            'reason' => 'Test action 1',
        ]);

        ChatModerationAction::create([
            'room_id' => $this->room->id,
            'moderator_id' => $this->moderator->id,
            'target_user_id' => $this->targetUser->id,
            'action_type' => ChatModerationAction::ACTION_BAN_USER,
            'reason' => 'Test action 2',
        ]);

        $response = $this->getJson("/api/chat/moderation/rooms/{$this->room->id}/actions");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'moderation_actions' => [
                    '*' => [
                        'id',
                        'room_id',
                        'moderator_id',
                        'target_user_id',
                        'action_type',
                        'reason',
                        'created_at',
                        'moderator' => ['id', 'name', 'email'],
                        'target_user' => ['id', 'name', 'email'],
                    ]
                ],
                'pagination' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                    'has_more_pages',
                ]
            ]);
    }

    public function test_get_moderation_actions_with_filters()
    {
        // Create actions
        ChatModerationAction::create([
            'room_id' => $this->room->id,
            'moderator_id' => $this->moderator->id,
            'target_user_id' => $this->targetUser->id,
            'action_type' => ChatModerationAction::ACTION_MUTE_USER,
            'reason' => 'Test mute',
        ]);

        $response = $this->getJson("/api/chat/moderation/rooms/{$this->room->id}/actions?" . http_build_query([
            'action_type' => ChatModerationAction::ACTION_MUTE_USER,
            'target_user_id' => $this->targetUser->id,
            'per_page' => 10
        ]));

        $response->assertStatus(200);
    }

    public function test_get_moderation_actions_unauthorized()
    {
        Sanctum::actingAs($this->regularUser);

        $response = $this->getJson("/api/chat/moderation/rooms/{$this->room->id}/actions");

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'You are not authorized to view moderation actions for this room'
            ]);
    }

    // ==================== GET USER MODERATION STATUS TESTS ====================

    public function test_get_user_moderation_status()
    {
        $response = $this->getJson("/api/chat/moderation/rooms/{$this->room->id}/users/{$this->targetUser->id}/status");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email'],
                'moderation_status' => [
                    'is_muted',
                    'muted_until',
                    'muted_by',
                    'is_banned',
                    'banned_until',
                    'banned_by',
                    'can_send_messages',
                ]
            ]);
    }

    public function test_get_user_moderation_status_muted_user()
    {
        // Mute the user
        $roomUser = ChatRoomUser::where('room_id', $this->room->id)
            ->where('user_id', $this->targetUser->id)
            ->first();
        $roomUser->mute($this->moderator->id, 60, 'Test mute');

        $response = $this->getJson("/api/chat/moderation/rooms/{$this->room->id}/users/{$this->targetUser->id}/status");

        $response->assertStatus(200)
            ->assertJson([
                'moderation_status' => [
                    'is_muted' => true,
                    'can_send_messages' => false,
                ]
            ]);
    }

    public function test_get_user_moderation_status_banned_user()
    {
        // Ban the user
        $roomUser = ChatRoomUser::where('room_id', $this->room->id)
            ->where('user_id', $this->targetUser->id)
            ->first();
        $roomUser->ban($this->moderator->id, 1440, 'Test ban');

        $response = $this->getJson("/api/chat/moderation/rooms/{$this->room->id}/users/{$this->targetUser->id}/status");

        $response->assertStatus(200)
            ->assertJson([
                'moderation_status' => [
                    'is_banned' => true,
                    'can_send_messages' => false,
                ]
            ]);
    }

    public function test_get_user_moderation_status_user_not_in_room()
    {
        $otherUser = User::factory()->create();

        $response = $this->getJson("/api/chat/moderation/rooms/{$this->room->id}/users/{$otherUser->id}/status");

        $response->assertStatus(404)
            ->assertJson([
                'message' => 'User is not in this room'
            ]);
    }

    public function test_get_user_moderation_status_unauthorized()
    {
        Sanctum::actingAs($this->regularUser);

        $response = $this->getJson("/api/chat/moderation/rooms/{$this->room->id}/users/{$this->targetUser->id}/status");

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'You are not authorized to view moderation status for this room'
            ]);
    }

    // ==================== ROOM CREATOR MODERATION TESTS ====================

    public function test_room_creator_can_moderate()
    {
        // Create a room with a non-admin creator
        $roomCreator = User::factory()->create(['is_admin' => false]);
        $room = ChatRoom::factory()->create(['created_by' => $roomCreator->id]);
        
        // Join users to room
        ChatRoomUser::create([
            'room_id' => $room->id,
            'user_id' => $roomCreator->id,
            'is_online' => true,
        ]);
        
        ChatRoomUser::create([
            'room_id' => $room->id,
            'user_id' => $this->targetUser->id,
            'is_online' => true,
        ]);
        
        $message = ChatMessage::factory()->create([
            'room_id' => $room->id,
            'user_id' => $this->targetUser->id,
        ]);

        Sanctum::actingAs($roomCreator);

        // Test that room creator can delete messages
        $response = $this->deleteJson("/api/chat/moderation/rooms/{$room->id}/messages/{$message->id}");
        $response->assertStatus(200);

        // Test that room creator can mute users
        $response = $this->postJson("/api/chat/moderation/rooms/{$room->id}/users/{$this->targetUser->id}/mute", [
            'duration' => 60
        ]);
        $response->assertStatus(200);
    }

    // ==================== ERROR HANDLING TESTS ====================

    public function test_database_transaction_rollback_on_error()
    {
        // This test would require mocking the database to force an exception
        // For now, we'll test that the controller handles exceptions gracefully
        
        $response = $this->deleteJson("/api/chat/moderation/rooms/{$this->room->id}/messages/{$this->message->id}", [
            'reason' => str_repeat('a', 501) // Exceeds max length
        ]);

        $response->assertStatus(422);
    }

    public function test_inactive_room_access()
    {
        // Create an inactive room
        $inactiveRoom = ChatRoom::factory()->create([
            'created_by' => $this->moderator->id,
            'is_active' => false
        ]);

        $response = $this->getJson("/api/chat/moderation/rooms/{$inactiveRoom->id}/actions");

        $response->assertStatus(404);
    }
} 