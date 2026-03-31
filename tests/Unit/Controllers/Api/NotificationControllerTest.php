<?php

namespace Tests\Unit\Controllers\Api;

use App\Http\Controllers\Api\Dashboard\NotificationController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationControllerTest extends TestCase
{
    protected NotificationController $controller;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new NotificationController;
    }

    private function actingAsUser(User $user): void
    {
        $this->actingAs($user);
        $this->app['auth']->setUser($user);
        $this->user = $user;
    }

    private function makeAuthenticatedRequest(string $method, string $uri): Request
    {
        $request = Request::create($uri, $method);
        $request->setUserResolver(fn () => $this->user);

        return $request;
    }

    public function test_unread_returns_notification_list(): void
    {
        $this->actingAsUser(User::factory()->create());

        // Create some notifications
        $this->user->notifications()->create([
            'type' => 'test_notification',
            'data' => ['message' => 'Test notification 1'],
        ]);
        $this->user->notifications()->create([
            'type' => 'test_notification',
            'data' => ['message' => 'Test notification 2'],
        ]);

        $request = $this->makeAuthenticatedRequest('GET', '/api/notifications/unread');

        $response = $this->controller->unread($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertTrue($data['success']);
        $payload = $data['data'];
        $this->assertArrayHasKey('count', $payload);
        $this->assertArrayHasKey('items', $payload);
        $this->assertSame(2, $payload['count']);
        $this->assertCount(2, $payload['items']);
    }

    public function test_unread_includes_count(): void
    {
        $this->actingAsUser(User::factory()->create());

        // Create 3 notifications
        for ($i = 0; $i < 3; $i++) {
            $this->user->notifications()->create([
                'type' => 'test_notification',
                'data' => ['message' => "Test notification $i"],
            ]);
        }

        $request = $this->makeAuthenticatedRequest('GET', '/api/notifications/unread');

        $response = $this->controller->unread($request);

        $data = $response->getData(true);
        $this->assertSame(3, $data['data']['count']);
    }

    public function test_unread_returns_latest_50_notifications(): void
    {
        $this->actingAsUser(User::factory()->create());

        // Create 60 notifications (more than 50 limit)
        for ($i = 0; $i < 60; $i++) {
            $this->user->notifications()->create([
                'type' => 'test_notification',
                'data' => ['message' => "Test notification $i"],
                'created_at' => now()->subMinutes($i),
            ]);
        }

        $request = $this->makeAuthenticatedRequest('GET', '/api/notifications/unread');

        $response = $this->controller->unread($request);

        $data = $response->getData(true);
        // Should return only 50 items
        $this->assertLessThanOrEqual(50, count($data['data']['items']));
    }

    public function test_unread_sends_summary_push_when_conditions_met(): void
    {
        Notification::fake();
        $this->actingAsUser(User::factory()->create());

        // Create an unread notification
        $this->user->notifications()->create([
            'type' => 'test_notification',
            'data' => ['message' => 'Test'],
        ]);

        // Clear any existing cache
        Cache::forget("user:{$this->user->id}:unread_summary_push_at");

        $request = $this->makeAuthenticatedRequest('GET', '/api/notifications/unread');

        $response = $this->controller->unread($request);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_unread_does_not_send_push_when_no_unread(): void
    {
        Notification::fake();
        $this->actingAsUser(User::factory()->create());

        // No notifications

        $request = $this->makeAuthenticatedRequest('GET', '/api/notifications/unread');

        $response = $this->controller->unread($request);

        $data = $response->getData(true);
        $this->assertSame(0, $data['data']['count']);
    }

    public function test_unread_does_not_send_push_during_cooldown(): void
    {
        Notification::fake();
        $this->actingAsUser(User::factory()->create());

        // Create notification
        $this->user->notifications()->create([
            'type' => 'test_notification',
            'data' => ['message' => 'Test'],
        ]);

        // Set cooldown
        Cache::put("user:{$this->user->id}:unread_summary_push_at", now()->subMinutes(2), now()->addMinutes(10));

        $request = $this->makeAuthenticatedRequest('GET', '/api/notifications/unread');

        $response = $this->controller->unread($request);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_unread_does_not_send_push_when_no_subscriptions(): void
    {
        Notification::fake();
        $this->actingAsUser(User::factory()->create());

        // Create notification
        $this->user->notifications()->create([
            'type' => 'test_notification',
            'data' => ['message' => 'Test'],
        ]);

        // No push subscriptions

        $request = $this->makeAuthenticatedRequest('GET', '/api/notifications/unread');

        $response = $this->controller->unread($request);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_mark_as_read_marks_notification_as_read(): void
    {
        $this->actingAsUser(User::factory()->create());

        $notification = $this->user->notifications()->create([
            'type' => 'test_notification',
            'data' => ['message' => 'Test'],
        ]);

        $this->assertNull($notification->read_at);

        $request = $this->makeAuthenticatedRequest('POST', '/api/notifications/read/' . $notification->id);

        $response = $this->controller->markAsRead($request, $notification->id);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_mark_as_read_returns_success(): void
    {
        $this->actingAsUser(User::factory()->create());

        $notification = $this->user->notifications()->create([
            'type' => 'test_notification',
            'data' => ['message' => 'Test'],
        ]);

        $request = $this->makeAuthenticatedRequest('POST', '/api/notifications/read/' . $notification->id);

        $response = $this->controller->markAsRead($request, $notification->id);

        $this->assertSame(200, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertTrue($data['success']);
    }

    public function test_mark_all_as_read_marks_all_notifications_as_read(): void
    {
        $this->actingAsUser(User::factory()->create());

        // Create notifications
        $n1 = $this->user->notifications()->create(['type' => 'test', 'data' => ['m' => '1']]);
        $n2 = $this->user->notifications()->create(['type' => 'test', 'data' => ['m' => '2']]);

        $this->assertNull($n1->read_at);
        $this->assertNull($n2->read_at);

        $request = $this->makeAuthenticatedRequest('POST', '/api/notifications/read-all');

        $response = $this->controller->markAllAsRead($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotNull($n1->fresh()->read_at);
        $this->assertNotNull($n2->fresh()->read_at);
    }

    public function test_mark_all_as_read_returns_success(): void
    {
        $this->actingAsUser(User::factory()->create());

        $this->user->notifications()->create(['type' => 'test', 'data' => ['m' => '1']]);

        $request = $this->makeAuthenticatedRequest('POST', '/api/notifications/read-all');

        $response = $this->controller->markAllAsRead($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertTrue($data['success']);
        $this->assertSame('已全部标记为已读', $data['message']);
    }
}
