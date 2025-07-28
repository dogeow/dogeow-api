<?php

namespace Tests\Feature;

use App\Models\ChatMessage;
use App\Models\ChatMessageReport;
use App\Models\ChatRoom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChatReportTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected User $admin;
    protected ChatRoom $room;
    protected ChatMessage $message;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create system user with ID 1 for auto-moderation
        User::factory()->create(['id' => 1, 'name' => 'System', 'email' => 'system@example.com']);
        
        $this->user = User::factory()->create();
        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->room = ChatRoom::factory()->create(['created_by' => $this->admin->id]);
        
        // Create a message from admin that user can report
        $this->message = ChatMessage::create([
            'room_id' => $this->room->id,
            'user_id' => $this->admin->id,
            'message' => 'This is a test message',
            'message_type' => 'text'
        ]);
    }

    public function test_user_can_report_message()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/chat/reports/rooms/{$this->room->id}/messages/{$this->message->id}", [
            'report_type' => ChatMessageReport::TYPE_INAPPROPRIATE_CONTENT,
            'reason' => 'This message contains inappropriate content'
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'report' => [
                    'id',
                    'message_id',
                    'reported_by',
                    'room_id',
                    'report_type',
                    'reason',
                    'status'
                ]
            ]);

        $this->assertDatabaseHas('chat_message_reports', [
            'message_id' => $this->message->id,
            'reported_by' => $this->user->id,
            'report_type' => ChatMessageReport::TYPE_INAPPROPRIATE_CONTENT,
            'status' => ChatMessageReport::STATUS_PENDING
        ]);
    }

    public function test_user_cannot_report_own_message()
    {
        Sanctum::actingAs($this->admin); // Admin trying to report their own message

        $response = $this->postJson("/api/chat/reports/rooms/{$this->room->id}/messages/{$this->message->id}", [
            'report_type' => ChatMessageReport::TYPE_SPAM,
            'reason' => 'Testing self-report'
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'You cannot report your own message'
            ]);
    }

    public function test_user_cannot_report_same_message_twice()
    {
        Sanctum::actingAs($this->user);

        // First report
        $this->postJson("/api/chat/reports/rooms/{$this->room->id}/messages/{$this->message->id}", [
            'report_type' => ChatMessageReport::TYPE_SPAM,
            'reason' => 'First report'
        ])->assertStatus(201);

        // Second report should fail
        $response = $this->postJson("/api/chat/reports/rooms/{$this->room->id}/messages/{$this->message->id}", [
            'report_type' => ChatMessageReport::TYPE_HARASSMENT,
            'reason' => 'Second report'
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'You have already reported this message'
            ]);
    }

    public function test_admin_can_view_room_reports()
    {
        // Create a report
        ChatMessageReport::create([
            'message_id' => $this->message->id,
            'reported_by' => $this->user->id,
            'room_id' => $this->room->id,
            'report_type' => ChatMessageReport::TYPE_SPAM,
            'reason' => 'Test report',
            'status' => ChatMessageReport::STATUS_PENDING
        ]);

        Sanctum::actingAs($this->admin);

        $response = $this->getJson("/api/chat/reports/rooms/{$this->room->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'reports' => [
                    '*' => [
                        'id',
                        'message_id',
                        'reported_by',
                        'report_type',
                        'reason',
                        'status',
                        'reporter',
                        'message'
                    ]
                ],
                'pagination'
            ]);
    }

    public function test_non_admin_cannot_view_reports()
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson("/api/chat/reports/rooms/{$this->room->id}");

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'You are not authorized to view reports for this room'
            ]);
    }

    public function test_admin_can_review_report()
    {
        $report = ChatMessageReport::create([
            'message_id' => $this->message->id,
            'reported_by' => $this->user->id,
            'room_id' => $this->room->id,
            'report_type' => ChatMessageReport::TYPE_INAPPROPRIATE_CONTENT,
            'reason' => 'Test report',
            'status' => ChatMessageReport::STATUS_PENDING
        ]);

        Sanctum::actingAs($this->admin);

        $response = $this->postJson("/api/chat/reports/{$report->id}/review", [
            'action' => 'resolve',
            'notes' => 'Report reviewed and resolved',
            'delete_message' => false,
            'mute_user' => false
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'report',
                'action',
                'actions_performed'
            ]);

        $this->assertDatabaseHas('chat_message_reports', [
            'id' => $report->id,
            'status' => ChatMessageReport::STATUS_RESOLVED,
            'reviewed_by' => $this->admin->id,
            'review_notes' => 'Report reviewed and resolved'
        ]);
    }

    public function test_report_stats_for_admin()
    {
        // Create some test reports
        ChatMessageReport::create([
            'message_id' => $this->message->id,
            'reported_by' => $this->user->id,
            'room_id' => $this->room->id,
            'report_type' => ChatMessageReport::TYPE_SPAM,
            'reason' => 'Spam report',
            'status' => ChatMessageReport::STATUS_PENDING
        ]);

        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/chat/reports/stats');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'total_reports',
                'pending_reports',
                'resolved_reports',
                'dismissed_reports',
                'report_types',
                'severity_breakdown',
                'top_reporters',
                'top_reported_users',
                'content_filter',
                'period_days'
            ]);
    }

    public function test_auto_moderation_on_multiple_reports()
    {
        // Create 3 reports for the same message to trigger auto-moderation
        for ($i = 0; $i < 3; $i++) {
            $reporter = User::factory()->create();
            ChatMessageReport::create([
                'message_id' => $this->message->id,
                'reported_by' => $reporter->id,
                'room_id' => $this->room->id,
                'report_type' => ChatMessageReport::TYPE_INAPPROPRIATE_CONTENT,
                'reason' => "Report #{$i}",
                'status' => ChatMessageReport::STATUS_PENDING
            ]);
        }

        Sanctum::actingAs($this->user);

        // This should trigger auto-moderation
        $response = $this->postJson("/api/chat/reports/rooms/{$this->room->id}/messages/{$this->message->id}", [
            'report_type' => ChatMessageReport::TYPE_HARASSMENT,
            'reason' => 'Final report that triggers auto-moderation'
        ]);

        if ($response->status() !== 201) {
            dump($response->json());
        }

        $response->assertStatus(201);

        // Check that the message was deleted
        $this->assertDatabaseMissing('chat_messages', [
            'id' => $this->message->id
        ]);

        // Check that all reports were auto-resolved (message_id will be null after deletion)
        $this->assertEquals(4, ChatMessageReport::where('room_id', $this->room->id)
            ->where('status', ChatMessageReport::STATUS_RESOLVED)
            ->whereNull('message_id') // Message was deleted, so message_id is now null
            ->count());
    }
}