<?php

namespace Tests\Unit\Models;

use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatMessageTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private ChatRoom $room;
    private ChatMessage $message;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->room = ChatRoom::factory()->create();
        $this->message = ChatMessage::factory()->create([
            'room_id' => $this->room->id,
            'user_id' => $this->user->id,
        ]);
    }

    public function test_chat_message_has_fillable_attributes()
    {
        $fillable = ['room_id', 'user_id', 'message', 'message_type'];
        
        $this->assertEquals($fillable, $this->message->getFillable());
    }

    public function test_chat_message_casts_attributes_correctly()
    {
        $casts = [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
        
        foreach ($casts as $attribute => $cast) {
            $this->assertEquals($cast, $this->message->getCasts()[$attribute]);
        }
    }

    public function test_chat_message_has_type_constants()
    {
        $this->assertEquals('text', ChatMessage::TYPE_TEXT);
        $this->assertEquals('system', ChatMessage::TYPE_SYSTEM);
    }

    public function test_chat_message_belongs_to_room()
    {
        $this->assertInstanceOf(ChatRoom::class, $this->message->room);
        $this->assertEquals($this->room->id, $this->message->room->id);
    }

    public function test_chat_message_belongs_to_user()
    {
        $this->assertInstanceOf(User::class, $this->message->user);
        $this->assertEquals($this->user->id, $this->message->user->id);
    }

    public function test_text_messages_scope_returns_only_text_messages()
    {
        $textMessage = ChatMessage::factory()->create([
            'room_id' => $this->room->id,
            'user_id' => $this->user->id,
            'message_type' => ChatMessage::TYPE_TEXT,
        ]);
        
        $systemMessage = ChatMessage::factory()->create([
            'room_id' => $this->room->id,
            'user_id' => $this->user->id,
            'message_type' => ChatMessage::TYPE_SYSTEM,
        ]);

        $textMessages = ChatMessage::textMessages()->get();

        $this->assertTrue($textMessages->contains($textMessage));
        $this->assertFalse($textMessages->contains($systemMessage));
    }

    public function test_system_messages_scope_returns_only_system_messages()
    {
        $textMessage = ChatMessage::factory()->create([
            'room_id' => $this->room->id,
            'user_id' => $this->user->id,
            'message_type' => ChatMessage::TYPE_TEXT,
        ]);
        
        $systemMessage = ChatMessage::factory()->create([
            'room_id' => $this->room->id,
            'user_id' => $this->user->id,
            'message_type' => ChatMessage::TYPE_SYSTEM,
        ]);

        $systemMessages = ChatMessage::systemMessages()->get();

        $this->assertFalse($systemMessages->contains($textMessage));
        $this->assertTrue($systemMessages->contains($systemMessage));
    }

    public function test_for_room_scope_returns_messages_for_specific_room()
    {
        $room2 = ChatRoom::factory()->create();
        
        $messageInRoom1 = ChatMessage::factory()->create([
            'room_id' => $this->room->id,
            'user_id' => $this->user->id,
        ]);
        
        $messageInRoom2 = ChatMessage::factory()->create([
            'room_id' => $room2->id,
            'user_id' => $this->user->id,
        ]);

        $room1Messages = ChatMessage::forRoom($this->room->id)->get();

        $this->assertTrue($room1Messages->contains($messageInRoom1));
        $this->assertFalse($room1Messages->contains($messageInRoom2));
    }

    public function test_is_text_message_returns_true_for_text_messages()
    {
        $textMessage = ChatMessage::factory()->create([
            'room_id' => $this->room->id,
            'user_id' => $this->user->id,
            'message_type' => ChatMessage::TYPE_TEXT,
        ]);

        $this->assertTrue($textMessage->isTextMessage());
    }

    public function test_is_text_message_returns_false_for_system_messages()
    {
        $systemMessage = ChatMessage::factory()->create([
            'room_id' => $this->room->id,
            'user_id' => $this->user->id,
            'message_type' => ChatMessage::TYPE_SYSTEM,
        ]);

        $this->assertFalse($systemMessage->isTextMessage());
    }

    public function test_is_system_message_returns_true_for_system_messages()
    {
        $systemMessage = ChatMessage::factory()->create([
            'room_id' => $this->room->id,
            'user_id' => $this->user->id,
            'message_type' => ChatMessage::TYPE_SYSTEM,
        ]);

        $this->assertTrue($systemMessage->isSystemMessage());
    }

    public function test_is_system_message_returns_false_for_text_messages()
    {
        $textMessage = ChatMessage::factory()->create([
            'room_id' => $this->room->id,
            'user_id' => $this->user->id,
            'message_type' => ChatMessage::TYPE_TEXT,
        ]);

        $this->assertFalse($textMessage->isSystemMessage());
    }

    public function test_chat_message_can_be_created_with_valid_data()
    {
        $messageData = [
            'room_id' => $this->room->id,
            'user_id' => $this->user->id,
            'message' => 'Hello, world!',
            'message_type' => ChatMessage::TYPE_TEXT,
        ];

        $message = ChatMessage::create($messageData);

        $this->assertInstanceOf(ChatMessage::class, $message);
        $this->assertEquals($this->room->id, $message->room_id);
        $this->assertEquals($this->user->id, $message->user_id);
        $this->assertEquals('Hello, world!', $message->message);
        $this->assertEquals(ChatMessage::TYPE_TEXT, $message->message_type);
    }

    public function test_chat_message_defaults_to_text_type()
    {
        $message = ChatMessage::factory()->create([
            'room_id' => $this->room->id,
            'user_id' => $this->user->id,
            'message' => 'Test message',
        ]);

        $this->assertEquals(ChatMessage::TYPE_TEXT, $message->message_type);
    }

    public function test_chat_message_can_be_system_type()
    {
        $message = ChatMessage::factory()->create([
            'room_id' => $this->room->id,
            'user_id' => $this->user->id,
            'message' => 'User joined the room',
            'message_type' => ChatMessage::TYPE_SYSTEM,
        ]);

        $this->assertEquals(ChatMessage::TYPE_SYSTEM, $message->message_type);
        $this->assertTrue($message->isSystemMessage());
    }
}