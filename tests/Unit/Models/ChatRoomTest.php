<?php

namespace Tests\Unit\Models;

use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\ChatRoomUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatRoomTest extends TestCase
{
    use RefreshDatabase;

    public function test_chat_room_can_be_created()
    {
        $user = User::factory()->create();
        $room = ChatRoom::factory()->create([
            'name' => 'Test Room',
            'description' => 'A test room',
            'created_by' => $user->id,
            'is_active' => true,
        ]);

        $this->assertInstanceOf(ChatRoom::class, $room);
        $this->assertEquals('Test Room', $room->name);
        $this->assertEquals('A test room', $room->description);
        $this->assertEquals($user->id, $room->created_by);
        $this->assertTrue($room->is_active);
    }

    public function test_creator_relationship()
    {
        $user = User::factory()->create();
        $room = ChatRoom::factory()->create(['created_by' => $user->id]);

        $this->assertInstanceOf(User::class, $room->creator);
        $this->assertEquals($user->id, $room->creator->id);
    }

    public function test_messages_relationship()
    {
        $room = ChatRoom::factory()->create();
        $message = ChatMessage::factory()->create(['room_id' => $room->id]);

        $this->assertInstanceOf(ChatMessage::class, $room->messages->first());
        $this->assertEquals($message->id, $room->messages->first()->id);
    }

    public function test_users_relationship()
    {
        $room = ChatRoom::factory()->create();
        $user = User::factory()->create();
        
        ChatRoomUser::factory()->create([
            'room_id' => $room->id,
            'user_id' => $user->id,
        ]);

        $this->assertInstanceOf(User::class, $room->users->first());
        $this->assertEquals($user->id, $room->users->first()->id);
    }

    public function test_online_users_relationship()
    {
        $room = ChatRoom::factory()->create();
        $onlineUser = User::factory()->create();
        $offlineUser = User::factory()->create();
        
        ChatRoomUser::factory()->create([
            'room_id' => $room->id,
            'user_id' => $onlineUser->id,
            'is_online' => true,
        ]);
        
        ChatRoomUser::factory()->create([
            'room_id' => $room->id,
            'user_id' => $offlineUser->id,
            'is_online' => false,
        ]);

        $onlineUsers = $room->onlineUsers;
        
        $this->assertCount(1, $onlineUsers);
        $this->assertEquals($onlineUser->id, $onlineUsers->first()->id);
    }

    public function test_active_scope()
    {
        ChatRoom::factory()->create(['is_active' => true]);
        ChatRoom::factory()->create(['is_active' => false]);

        $activeRooms = ChatRoom::active()->get();

        $this->assertCount(1, $activeRooms);
        $this->assertTrue($activeRooms->first()->is_active);
    }

    public function test_online_count_attribute()
    {
        $room = ChatRoom::factory()->create();
        $onlineUser = User::factory()->create();
        $offlineUser = User::factory()->create();
        
        ChatRoomUser::factory()->create([
            'room_id' => $room->id,
            'user_id' => $onlineUser->id,
            'is_online' => true,
        ]);
        
        ChatRoomUser::factory()->create([
            'room_id' => $room->id,
            'user_id' => $offlineUser->id,
            'is_online' => false,
        ]);

        $this->assertEquals(1, $room->online_count);
    }

    public function test_is_active_is_casted_to_boolean()
    {
        $room = ChatRoom::factory()->create(['is_active' => 1]);

        $this->assertIsBool($room->is_active);
        $this->assertTrue($room->is_active);
    }

    public function test_created_at_and_updated_at_are_casted_to_datetime()
    {
        $room = ChatRoom::factory()->create();

        $this->assertInstanceOf(\Carbon\Carbon::class, $room->created_at);
        $this->assertInstanceOf(\Carbon\Carbon::class, $room->updated_at);
    }

    public function test_room_can_have_multiple_messages()
    {
        $room = ChatRoom::factory()->create();
        $message1 = ChatMessage::factory()->create(['room_id' => $room->id]);
        $message2 = ChatMessage::factory()->create(['room_id' => $room->id]);

        $this->assertCount(2, $room->messages);
        $this->assertContains($message1->id, $room->messages->pluck('id'));
        $this->assertContains($message2->id, $room->messages->pluck('id'));
    }

    public function test_room_can_have_multiple_users()
    {
        $room = ChatRoom::factory()->create();
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        
        ChatRoomUser::factory()->create([
            'room_id' => $room->id,
            'user_id' => $user1->id,
        ]);
        
        ChatRoomUser::factory()->create([
            'room_id' => $room->id,
            'user_id' => $user2->id,
        ]);

        $this->assertCount(2, $room->users);
        $this->assertContains($user1->id, $room->users->pluck('id'));
        $this->assertContains($user2->id, $room->users->pluck('id'));
    }
}