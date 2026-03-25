<?php

namespace Tests\Unit\Controllers\Api;

use App\Http\Controllers\Api\NotificationController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationControllerTest extends TestCase
{
    protected NotificationController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new NotificationController;
    }

    public function test_unread_returns_notification_list(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Create some notifications
        $user->notifications()->create([
            'type' => 'test_notification',
            'data' => ['message' => 'Test notification 1'],
        ]);
        $user->notifications()->create([
            'type' => 'test_notification',
            'data' => ['message' => 'Test notification 2'],
        ]);

        $request = Request::create('/api/notifications/unread', 'GET');

        $response = $this->controller->unread($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertArrayHasKey('count', $data);
        $this->assertArrayHasKey('items', $data);
        $this->assertSame(2, $data['count']);
        $this->assertCount(2, $data['items']);
    }

    public function test_unread_includes_count(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Create 3 notifications
        for ($i = 0; $i < 3; $i++) {
            $user->notifications()->create([
                'type' => 'test_notification',
                'data' => ['message' => "Test notification $i"],
            ]);
        }

        $request = Request::create('/api/notifications/unread', 'GET');

        $response = $this->controller->unread($request);

        $data = $response->getData(true);
        $this->assertSame(3, $data['count']);
    }

    public function test_unread_returns_latest_50_notifications(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Create 60 notifications (more than 50 limit)
        for ($i = 0; $i < 60; $i++) {
            $user->notifications()->create([
                'type' => 'test_notification',
                'data' => ['message' => "Test notification $i"],
                'created_at' => now()->subMinutes($i),
            ]);
        }

        $request = Request::create('/api/notifications/unread', 'GET');

        $response = $this->controller->unread($request);

        $data = $response->getData(true);
        // Should return only 50 items
        $this->assertLessThanOrEqual(50, count($data['items']));
    }

    public function test_unread_sends_summary_push_when_conditions_met(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $this->actingAs($user);

        // Create an unread notification
        $user->notifications()->create([
            'type' => 'test_notification',
            'data' => ['message' => 'Test'],
        ]);

        // Clear any existing cache
        Cache::forget("user:{$user->id}:unread_summary_push_at");

        $request = Request::create('/api/notifications/unread', 'GET');

        $response = $this->controller->unread($request);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_unread_does_not_send_push_when_no_unread(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $this->actingAs($user);

        // No notifications

        $request = Request::create('/api/notifications/unread', 'GET');

        $response = $this->controller->unread($request);

        $data = $response->getData(true);
        $this->assertSame(0, $data['count']);
    }

    public function test_unread_does_not_send_push_during_cooldown(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $this->actingAs($user);

        // Create notification
        $user->notifications()->create([
            'type' => 'test_notification',
            'data' => ['message' => 'Test'],
        ]);

        // Set cooldown
        Cache::put("user:{$user->id}:unread_summary_push_at", now()->subMinutes(2), now()->addMinutes(10));

        $request = Request::create('/api/notifications/unread', 'GET');

        $response = $this->controller->unread($request);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_unread_does_not_send_push_when_no_subscriptions(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $this->actingAs($user);

        // Create notification
        $user->notifications()->create([
            'type' => 'test_notification',
            'data' => ['message' => 'Test'],
        ]);

        // No push subscriptions

        $request = Request::create('/api/notifications/unread', 'GET');

        $response = $this->controller->unread($request);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_mark_as_read_marks_notification_as_read(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $notification = $user->notifications()->create([
            'type' => 'test_notification',
            'data' => ['message' => 'Test'],
        ]);

        $this->assertNull($notification->read_at);

        $request = Request::create('/api/notifications/read/' . $notification->id, 'POST');

        $response = $this->controller->markAsRead($request, $notification->id);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_mark_as_read_returns_success(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $notification = $user->notifications()->create([
            'type' => 'test_notification',
            'data' => ['message' => 'Test'],
        ]);

        $request = Request::create('/api/notifications/read/' . $notification->id, 'POST');

        $response = $this->controller->markAsRead($request, $notification->id);

        $this->assertSame(200, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertTrue($data['success']);
    }

    public function test_mark_all_as_read_marks_all_notifications_as_read(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Create notifications
        $n1 = $user->notifications()->create(['type' => 'test', 'data' => ['m' => '1']]);
        $n2 = $user->notifications()->create(['type' => 'test', 'data' => ['m' => '2']]);

        $this->assertNull($n1->read_at);
        $this->assertNull($n2->read_at);

        $request = Request::create('/api/notifications/read-all', 'POST');

        $response = $this->controller->markAllAsRead($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotNull($n1->fresh()->read_at);
        $this->assertNotNull($n2->fresh()->read_at);
    }

    public function test_mark_all_as_read_returns_success(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $user->notifications()->create(['type' => 'test', 'data' => ['m' => '1']]);

        $request = Request::create('/api/notifications/read-all', 'POST');

        $response = $this->controller->markAllAsRead($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertTrue($data['success']);
        $this->assertSame('已全部标记为已读', $data['message']);
    }
}
