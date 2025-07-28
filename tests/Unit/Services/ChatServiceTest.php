<?php

namespace Tests\Unit\Services;

use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\User;
use App\Services\ChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatServiceTest extends TestCase
{
    use RefreshDatabase;

    private ChatService $chatService;
    private User $user;
    private ChatRoom $room;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->chatService = new ChatService();
        $this->user = User::factory()->create(['name' => 'testuser']);
        $this->room = ChatRoom::factory()->create();
    }

    public function test_validate_message_with_valid_input()
    {
        $result = $this->chatService->validateMessage('Hello world!');
        
        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
        $this->assertEquals('Hello world!', $result['sanitized_message']);
    }

    public function test_validate_message_with_empty_input()
    {
        $result = $this->chatService->validateMessage('   ');
        
        $this->assertFalse($result['valid']);
        $this->assertContains('Message cannot be empty', $result['errors']);
    }

    public function test_validate_message_with_too_long_input()
    {
        $longMessage = str_repeat('a', 1001);
        $result = $this->chatService->validateMessage($longMessage);
        
        $this->assertFalse($result['valid']);
        $this->assertContains('Message cannot exceed 1000 characters', $result['errors']);
    }

    public function test_sanitize_message_removes_html_tags()
    {
        $message = '<script>alert("xss")</script>Hello <b>world</b>!';
        $sanitized = $this->chatService->sanitizeMessage($message);
        
        $this->assertEquals('alert(&quot;xss&quot;)Hello world!', $sanitized);
    }

    public function test_sanitize_message_converts_special_characters()
    {
        $message = 'Hello & "world" <test>';
        $sanitized = $this->chatService->sanitizeMessage($message);
        
        $this->assertEquals('Hello &amp; &quot;world&quot;', $sanitized);
    }

    public function test_process_mentions_finds_existing_users()
    {
        $message = 'Hello @testuser, how are you?';
        $mentions = $this->chatService->processMentions($message);
        
        $this->assertCount(1, $mentions);
        $this->assertEquals($this->user->id, $mentions[0]['user_id']);
        $this->assertEquals('testuser', $mentions[0]['username']);
    }

    public function test_process_mentions_ignores_nonexistent_users()
    {
        $message = 'Hello @nonexistentuser, how are you?';
        $mentions = $this->chatService->processMentions($message);
        
        $this->assertEmpty($mentions);
    }

    public function test_process_mentions_handles_multiple_mentions()
    {
        $user2 = User::factory()->create(['name' => 'user2']);
        $message = 'Hello @testuser and @user2!';
        $mentions = $this->chatService->processMentions($message);
        
        $this->assertCount(2, $mentions);
        $userIds = array_column($mentions, 'user_id');
        $this->assertContains($this->user->id, $userIds);
        $this->assertContains($user2->id, $userIds);
    }

    public function test_format_message_highlights_mentions()
    {
        $message = 'Hello @testuser!';
        $mentions = [
            [
                'user_id' => $this->user->id,
                'username' => 'testuser',
                'email' => $this->user->email
            ]
        ];
        
        $formatted = $this->chatService->formatMessage($message, $mentions);
        
        $this->assertStringContainsString('<mention data-user-id="' . $this->user->id . '">@testuser</mention>', $formatted);
    }

    public function test_format_message_converts_emoticons()
    {
        $message = 'Hello :) I am happy :D';
        $formatted = $this->chatService->formatMessage($message);
        
        $this->assertEquals('Hello 😊 I am happy 😃', $formatted);
    }

    public function test_process_message_creates_valid_message()
    {
        $result = $this->chatService->processMessage(
            $this->room->id,
            $this->user->id,
            'Hello world!'
        );
        
        $this->assertTrue($result['success']);
        $this->assertInstanceOf(ChatMessage::class, $result['message']);
        $this->assertEquals('Hello world!', $result['original_message']);
        $this->assertEmpty($result['mentions']);
    }

    public function test_process_message_with_mentions()
    {
        $result = $this->chatService->processMessage(
            $this->room->id,
            $this->user->id,
            'Hello @testuser!'
        );
        
        $this->assertTrue($result['success']);
        $this->assertCount(1, $result['mentions']);
        $this->assertEquals($this->user->id, $result['mentions'][0]['user_id']);
    }

    public function test_process_message_fails_with_invalid_input()
    {
        $result = $this->chatService->processMessage(
            $this->room->id,
            $this->user->id,
            ''
        );
        
        $this->assertFalse($result['success']);
        $this->assertContains('Message cannot be empty', $result['errors']);
    }

    public function test_create_system_message()
    {
        $systemUser = User::factory()->create(['name' => 'system']);
        $message = $this->chatService->createSystemMessage($this->room->id, 'User joined the room', $systemUser->id);
        
        $this->assertInstanceOf(ChatMessage::class, $message);
        $this->assertEquals(ChatMessage::TYPE_SYSTEM, $message->message_type);
        $this->assertEquals($systemUser->id, $message->user_id);
        $this->assertEquals('User joined the room', $message->message);
    }

    public function test_get_recent_messages()
    {
        // Create some test messages
        ChatMessage::factory()->count(5)->create([
            'room_id' => $this->room->id,
            'user_id' => $this->user->id
        ]);
        
        $messages = $this->chatService->getRecentMessages($this->room->id, 3);
        
        $this->assertCount(3, $messages);
        // Messages should be in chronological order (oldest first)
        $this->assertTrue($messages[0]->created_at <= $messages[1]->created_at);
    }

    public function test_search_messages()
    {
        ChatMessage::factory()->create([
            'room_id' => $this->room->id,
            'user_id' => $this->user->id,
            'message' => 'Hello world'
        ]);
        
        ChatMessage::factory()->create([
            'room_id' => $this->room->id,
            'user_id' => $this->user->id,
            'message' => 'Goodbye everyone'
        ]);
        
        $results = $this->chatService->searchMessages($this->room->id, 'Hello');
        
        $this->assertCount(1, $results);
        $this->assertStringContainsString('Hello world', $results->first()->message);
    }

    public function test_get_message_stats()
    {
        // Create test messages
        ChatMessage::factory()->count(3)->create([
            'room_id' => $this->room->id,
            'user_id' => $this->user->id,
            'message_type' => ChatMessage::TYPE_TEXT
        ]);
        
        $systemUser = User::factory()->create(['name' => 'system']);
        ChatMessage::factory()->create([
            'room_id' => $this->room->id,
            'user_id' => $systemUser->id,
            'message_type' => ChatMessage::TYPE_SYSTEM
        ]);
        
        $stats = $this->chatService->getMessageStats($this->room->id);
        
        $this->assertEquals(4, $stats['total_messages']);
        $this->assertEquals(3, $stats['text_messages']);
        $this->assertEquals(1, $stats['system_messages']);
        $this->assertCount(1, $stats['top_users']);
    }
}