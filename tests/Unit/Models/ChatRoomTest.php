<?php

namespace Tests\Unit\Models;

use App\Models\ChatRoom;
use App\Models\ChatMessage;
use App\Models\ChatRoomUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatRoomTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private ChatRoom $room;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->room = ChatRoom::factory()->create([
            'created_by' => $this->user->id,
        ]);
    }

    public function test_chat_room_has_fillable_attributes()
    {
        $fillable = ['name', 'description', 'created_by', 'is_active'];
        
        $this->assertEquals($fillable, $this->room->getFillable());
    }

    public function test_chat_room_casts_attributes_correctly()
    {
        $casts = [
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
        
        foreach ($casts as $attribute => $cast) {
            $this->assertEquals($cast, $this->room->getCasts()[$attribute]);
        }
    }

    public function test_chat_room_belongs_to_creator()
    {
        $this->assertInstanceOf(User::class, $this->room->creator);
        $this->assertEquals($this->user->id, $this->room->creator->id);
    }

    public function test_chat_room_has_many_messages()
    {
        $message = ChatMessage::factory()->create([
            'room_id' => $this->room->id,
            'user_id' => $this->user->id,
        ]);

        $this->assertTrue($this->room->messages->contains($message));
        $this->assertInstanceOf(ChatMessage::class, $this->room->messages->first());
    }

    public function test_chat_room_belongs_to_many_users()
    {
        $user2 = User::factory()->create();
        
        ChatRoomUser::create([
            'room_id' => $this->room->id,
            'user_id' => $this->user->id,
            'is_online' => true,
        ]);
        
        ChatRoomUser::create([
            'room_id' => $this->room->id,
            'user_id' => $user2->id,
            'is_online' => false,
        ]);

        $this->assertCount(2, $this->room->users);
        $this->assertTrue($this->room->users->contains($this->user));
        $this->assertTrue($this->room->users->contains($user2));
    }

    public function test_chat_room_has_online_users_relationship()
    {
        $user2 = User::factory()->create();
        
        ChatRoomUser::create([
            'room_id' => $this->room->id,
            'user_id' => $this->user->id,
            'is_online' => true,
        ]);
        
        ChatRoomUser::create([
            'room_id' => $this->room->id,
            'user_id' => $user2->id,
            'is_online' => false,
        ]);

        $onlineUsers = $this->room->onlineUsers;
        
        $this->assertCount(1, $onlineUsers);
        $this->assertTrue($onlineUsers->contains($this->user));
        $this->assertFalse($onlineUsers->contains($user2));
    }

    public function test_active_scope_returns_only_active_rooms()
    {
        $activeRoom = ChatRoom::factory()->create(['is_active' => true]);
        $inactiveRoom = ChatRoom::factory()->create(['is_active' => false]);

        $activeRooms = ChatRoom::active()->get();

        $this->assertTrue($activeRooms->contains($activeRoom));
        $this->assertFalse($activeRooms->contains($inactiveRoom));
    }

    public function test_online_count_attribute_returns_correct_count()
    {
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();
        
        ChatRoomUser::create([
            'room_id' => $this->room->id,
            'user_id' => $this->user->id,
            'is_online' => true,
        ]);
        
        ChatRoomUser::create([
            'room_id' => $this->room->id,
            'user_id' => $user2->id,
            'is_online' => true,
        ]);
        
        ChatRoomUser::create([
            'room_id' => $this->room->id,
            'user_id' => $user3->id,
            'is_online' => false,
        ]);

        $this->assertEquals(2, $this->room->online_count);
    }

    public function test_chat_room_can_be_created_with_valid_data()
    {
        $roomData = [
            'name' => 'Test Room',
            'description' => 'A test chat room',
            'created_by' => $this->user->id,
            'is_active' => true,
        ];

        $room = ChatRoom::create($roomData);

        $this->assertInstanceOf(ChatRoom::class, $room);
        $this->assertEquals('Test Room', $room->name);
        $this->assertEquals('A test chat room', $room->description);
        $this->assertEquals($this->user->id, $room->created_by);
        $this->assertTrue($room->is_active);
    }

    public function test_chat_room_defaults_to_active()
    {
        $room = ChatRoom::factory()->create([
            'created_by' => $this->user->id,
        ]);

        $this->assertTrue($room->is_active);
    }

    public function test_chat_room_pivot_includes_timestamps()
    {
        ChatRoomUser::create([
            'room_id' => $this->room->id,
            'user_id' => $this->user->id,
            'is_online' => true,
            'joined_at' => now(),
            'last_seen_at' => now(),
        ]);

        $user = $this->room->users->first();
        
        $this->assertNotNull($user->pivot->joined_at);
        $this->assertNotNull($user->pivot->last_seen_at);
        $this->assertNotNull($user->pivot->created_at);
        $this->assertNotNull($user->pivot->updated_at);
    }
}