<?php

namespace Tests\Feature\Controllers;

use App\Models\Chat\ChatMessage;
use App\Models\Chat\ChatMessageReport;
use App\Models\Chat\ChatRoom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChatReportControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private User $messageUser;
    private ChatRoom $room;
    private ChatMessage $message;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->messageUser = User::factory()->create();
        $this->room = ChatRoom::factory()->create(['created_by' => $this->user->id]);
        $this->message = ChatMessage::factory()->create([
            'room_id' => $this->room->id,
            'user_id' => $this->messageUser->id,
        ]);
        
        Sanctum::actingAs($this->user);
    }

    public function test_report_message_success()
    {
        $reportData = [
            'report_type' => ChatMessageReport::TYPE_INAPPROPRIATE_CONTENT,
            'reason' => 'Inappropriate content'
        ];

        $response = $this->postJson("/api/chat/reports/rooms/{$this->room->id}/messages/{$this->message->id}", $reportData);

        $response->assertStatus(201);
        $response->assertJson([
            'message' => 'Message reported successfully'
        ]);

        $this->assertDatabaseHas('chat_message_reports', [
            'message_id' => $this->message->id,
            'reported_by' => $this->user->id,
            'room_id' => $this->room->id,
            'report_type' => ChatMessageReport::TYPE_INAPPROPRIATE_CONTENT,
            'reason' => 'Inappropriate content',
            'status' => ChatMessageReport::STATUS_PENDING,
        ]);
    }

    public function test_report_message_cannot_report_own_message()
    {
        $ownMessage = ChatMessage::factory()->create([
            'room_id' => $this->room->id,
            'user_id' => $this->user->id,
        ]);

        $reportData = [
            'report_type' => ChatMessageReport::TYPE_SPAM,
            'reason' => 'Test'
        ];

        $response = $this->postJson("/api/chat/reports/rooms/{$this->room->id}/messages/{$ownMessage->id}", $reportData);

        $response->assertStatus(422);
        $response->assertJson([
            'message' => 'You cannot report your own message'
        ]);
    }

    public function test_report_message_validates_report_type()
    {
        $reportData = [
            'report_type' => 'invalid_type',
            'reason' => 'Test'
        ];

        $response = $this->postJson("/api/chat/reports/rooms/{$this->room->id}/messages/{$this->message->id}", $reportData);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['report_type']);
    }

    public function test_report_message_validates_reason_length()
    {
        $reportData = [
            'report_type' => ChatMessageReport::TYPE_SPAM,
            'reason' => str_repeat('a', 501), // Too long
        ];

        $response = $this->postJson("/api/chat/reports/rooms/{$this->room->id}/messages/{$this->message->id}", $reportData);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['reason']);
    }

    public function test_report_message_prevents_duplicate_reports()
    {
        // Create an existing report
        ChatMessageReport::create([
            'message_id' => $this->message->id,
            'reported_by' => $this->user->id,
            'room_id' => $this->room->id,
            'report_type' => ChatMessageReport::TYPE_SPAM,
            'status' => ChatMessageReport::STATUS_PENDING,
        ]);

        $reportData = [
            'report_type' => ChatMessageReport::TYPE_HARASSMENT,
            'reason' => 'Another report'
        ];

        $response = $this->postJson("/api/chat/reports/rooms/{$this->room->id}/messages/{$this->message->id}", $reportData);

        $response->assertStatus(422);
        $response->assertJson([
            'message' => 'You have already reported this message'
        ]);
    }

    public function test_get_room_reports_returns_reports()
    {
        // Create some reports
        ChatMessageReport::create([
            'message_id' => $this->message->id,
            'reported_by' => $this->user->id,
            'room_id' => $this->room->id,
            'report_type' => ChatMessageReport::TYPE_SPAM,
            'status' => ChatMessageReport::STATUS_PENDING,
        ]);

        $response = $this->getJson("/api/chat/reports/rooms/{$this->room->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'reports' => [
                '*' => [
                    'id',
                    'message_id',
                    'reported_by',
                    'room_id',
                    'report_type',
                    'reason',
                    'status',
                    'created_at'
                ]
            ]
        ]);
    }

    public function test_get_all_reports_returns_all_reports()
    {
        // Make user admin for this test
        $this->user->update(['is_admin' => true]);
        
        // Create reports in different rooms
        $otherRoom = ChatRoom::factory()->create();
        $otherMessage = ChatMessage::factory()->create(['room_id' => $otherRoom->id]);

        ChatMessageReport::create([
            'message_id' => $this->message->id,
            'reported_by' => $this->user->id,
            'room_id' => $this->room->id,
            'report_type' => ChatMessageReport::TYPE_SPAM,
            'status' => ChatMessageReport::STATUS_PENDING,
        ]);

        ChatMessageReport::create([
            'message_id' => $otherMessage->id,
            'reported_by' => $this->user->id,
            'room_id' => $otherRoom->id,
            'report_type' => ChatMessageReport::TYPE_HARASSMENT,
            'status' => ChatMessageReport::STATUS_PENDING,
        ]);

        $response = $this->getJson('/api/chat/reports');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'reports' => [
                '*' => [
                    'id',
                    'message_id',
                    'reported_by',
                    'room_id',
                    'report_type',
                    'reason',
                    'status'
                ]
            ]
        ]);

        $data = $response->json('reports');
        $this->assertCount(2, $data);
    }

    public function test_review_report_approves_report()
    {
        $report = ChatMessageReport::create([
            'message_id' => $this->message->id,
            'reported_by' => $this->user->id,
            'room_id' => $this->room->id,
            'report_type' => ChatMessageReport::TYPE_SPAM,
            'status' => ChatMessageReport::STATUS_PENDING,
        ]);

        $reviewData = [
            'action' => 'resolve',
            'notes' => 'Content violates community guidelines'
        ];

        $response = $this->postJson("/api/chat/reports/{$report->id}/review", $reviewData);

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Report reviewed successfully'
        ]);

        $this->assertDatabaseHas('chat_message_reports', [
            'id' => $report->id,
            'status' => ChatMessageReport::STATUS_RESOLVED,
        ]);
    }

    public function test_review_report_rejects_report()
    {
        $report = ChatMessageReport::create([
            'message_id' => $this->message->id,
            'reported_by' => $this->user->id,
            'room_id' => $this->room->id,
            'report_type' => ChatMessageReport::TYPE_SPAM,
            'status' => ChatMessageReport::STATUS_PENDING,
        ]);

        $reviewData = [
            'action' => 'dismiss',
            'notes' => 'False report'
        ];

        $response = $this->postJson("/api/chat/reports/{$report->id}/review", $reviewData);

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Report reviewed successfully'
        ]);

        $this->assertDatabaseHas('chat_message_reports', [
            'id' => $report->id,
            'status' => ChatMessageReport::STATUS_DISMISSED,
        ]);
    }

    public function test_review_report_validates_action()
    {
        $report = ChatMessageReport::create([
            'message_id' => $this->message->id,
            'reported_by' => $this->user->id,
            'room_id' => $this->room->id,
            'report_type' => ChatMessageReport::TYPE_SPAM,
            'status' => ChatMessageReport::STATUS_PENDING,
        ]);

        $reviewData = [
            'action' => 'invalid_action',
            'moderator_notes' => 'Test'
        ];

        $response = $this->postJson("/api/chat/reports/{$report->id}/review", $reviewData);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['action']);
    }

    public function test_get_report_stats_returns_statistics()
    {
        // Make user admin for this test
        $this->user->update(['is_admin' => true]);
        
        // Create reports with different statuses
        ChatMessageReport::create([
            'message_id' => $this->message->id,
            'reported_by' => $this->user->id,
            'room_id' => $this->room->id,
            'report_type' => ChatMessageReport::TYPE_SPAM,
            'status' => ChatMessageReport::STATUS_PENDING,
        ]);

        ChatMessageReport::create([
            'message_id' => $this->message->id,
            'reported_by' => $this->user->id,
            'room_id' => $this->room->id,
            'report_type' => ChatMessageReport::TYPE_HARASSMENT,
            'status' => ChatMessageReport::STATUS_REVIEWED,
        ]);

        $response = $this->getJson('/api/chat/reports/stats');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'total_reports',
            'pending_reports',
            'resolved_reports',
            'dismissed_reports',
            'report_types',
            'severity_breakdown'
        ]);
    }

    public function test_report_message_includes_metadata()
    {
        $reportData = [
            'report_type' => ChatMessageReport::TYPE_INAPPROPRIATE_CONTENT,
            'reason' => 'Test report'
        ];

        $response = $this->postJson("/api/chat/reports/rooms/{$this->room->id}/messages/{$this->message->id}", $reportData);

        $response->assertStatus(201);

        $report = ChatMessageReport::where('message_id', $this->message->id)->first();
        $this->assertNotNull($report);
        $this->assertArrayHasKey('reporter_ip', $report->metadata);
        $this->assertArrayHasKey('user_agent', $report->metadata);
        $this->assertArrayHasKey('message_content', $report->metadata);
    }
} 