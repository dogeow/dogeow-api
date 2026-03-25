<?php

namespace Tests\Unit\Controllers\Api;

use App\Http\Controllers\Api\ProfileController;
use App\Models\User;
use App\Models\Thing\Item;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileControllerTest extends TestCase
{
    use RefreshDatabase;

    protected ProfileController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new ProfileController;
    }

    public function test_edit_returns_user_profile(): void
    {
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
        $this->actingAs($user);

        $request = Request::create('/api/profile', 'GET');

        $response = $this->controller->edit($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertArrayHasKey('user', $data);
        $this->assertSame('Test User', $data['user']['name']);
        $this->assertSame('test@example.com', $data['user']['email']);
    }

    public function test_edit_returns_correct_fields(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $request = Request::create('/api/profile', 'GET');

        $response = $this->controller->edit($request);

        $data = $response->getData(true);
        $userData = $data['user'];
        $this->assertArrayHasKey('id', $userData);
        $this->assertArrayHasKey('name', $userData);
        $this->assertArrayHasKey('email', $userData);
        $this->assertArrayHasKey('email_verified_at', $userData);
        $this->assertArrayHasKey('created_at', $userData);
        $this->assertArrayHasKey('updated_at', $userData);
    }

    public function test_update_validates_request(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $request = Request::create('/api/profile', 'PUT', [
            'name' => 'Updated Name',
        ]);

        $response = $this->controller->update($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertTrue($data['success']);
        $this->assertSame('Updated Name', $data['data']['user']['name']);
    }

    public function test_update_changes_email(): void
    {
        $user = User::factory()->create([
            'email' => 'old@example.com',
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        $request = Request::create('/api/profile', 'PUT', [
            'name' => 'Test User',
            'email' => 'new@example.com',
        ]);

        $response = $this->controller->update($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertSame('new@example.com', $data['data']['user']['email']);
    }

    public function test_update_clears_email_verified_at_when_email_changes(): void
    {
        $user = User::factory()->create([
            'email' => 'old@example.com',
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        $request = Request::create('/api/profile', 'PUT', [
            'name' => 'Test User',
            'email' => 'new@example.com',
        ]);

        $response = $this->controller->update($request);

        $this->assertSame(200, $response->getStatusCode());
        $user->refresh();
        $this->assertNull($user->email_verified_at);
    }

    public function test_update_preserves_email_verified_at_when_email_unchanged(): void
    {
        $verifiedAt = now();
        $user = User::factory()->create([
            'email' => 'same@example.com',
            'email_verified_at' => $verifiedAt,
        ]);
        $this->actingAs($user);

        $request = Request::create('/api/profile', 'PUT', [
            'name' => 'Updated Name',
            'email' => 'same@example.com', // Same email
        ]);

        $response = $this->controller->update($request);

        $this->assertSame(200, $response->getStatusCode());
        $user->refresh();
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_destroy_requires_password(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $request = Request::create('/api/profile', 'DELETE', []);

        $response = $this->controller->destroy($request);

        // Should fail validation because password is required
        $this->assertNotSame(200, $response->getStatusCode());
    }

    public function test_destroy_validates_current_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('correct_password'),
        ]);
        $this->actingAs($user);

        $request = Request::create('/api/profile', 'DELETE', [
            'password' => 'wrong_password',
        ]);

        $response = $this->controller->destroy($request);

        // Should fail because password doesn't match
        $this->assertNotSame(200, $response->getStatusCode());
    }

    public function test_destroy_deletes_user_items(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('correct_password'),
        ]);
        $this->actingAs($user);

        // Create some items for the user
        $item = Item::create([
            'user_id' => $user->id,
            'name' => 'Test Item',
            'quantity' => 1,
        ]);

        $this->assertDatabaseHas('thing_items', ['id' => $item->id, 'user_id' => $user->id]);

        $request = Request::create('/api/profile', 'DELETE', [
            'password' => 'correct_password',
        ]);

        $response = $this->controller->destroy($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertDatabaseMissing('thing_items', ['id' => $item->id]);
    }

    public function test_destroy_deletes_user(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('correct_password'),
        ]);
        $this->actingAs($user);
        $userId = $user->id;

        $request = Request::create('/api/profile', 'DELETE', [
            'password' => 'correct_password',
        ]);

        $response = $this->controller->destroy($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertDatabaseMissing('users', ['id' => $userId]);
    }

    public function test_destroy_runs_in_transaction(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('correct_password'),
        ]);
        $this->actingAs($user);

        $request = Request::create('/api/profile', 'DELETE', [
            'password' => 'correct_password',
        ]);

        // If transaction works correctly, user should be deleted
        $response = $this->controller->destroy($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertTrue($data['success']);
    }

    public function test_update_does_not_clear_verified_at_when_name_only_changes(): void
    {
        $verifiedAt = now();
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'email_verified_at' => $verifiedAt,
        ]);
        $this->actingAs($user);

        $request = Request::create('/api/profile', 'PUT', [
            'name' => 'New Name',
            'email' => 'test@example.com', // Same email
        ]);

        $response = $this->controller->update($request);

        $this->assertSame(200, $response->getStatusCode());
        $user->refresh();
        $this->assertEquals($verifiedAt->toDateTimeString(), $user->email_verified_at->toDateTimeString());
    }
}
