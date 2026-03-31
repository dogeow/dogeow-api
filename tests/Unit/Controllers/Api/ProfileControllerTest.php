<?php

namespace Tests\Unit\Controllers\Api;

use App\Models\Thing\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_edit_returns_user_profile(): void
    {
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $response = $this->actingAs($user)->getJson('/api/profile');

        $response->assertStatus(200);
        $data = $response->json('data.user');
        $this->assertSame('Test User', $data['name']);
        $this->assertSame('test@example.com', $data['email']);
    }

    public function test_edit_returns_correct_fields(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/profile');

        $response->assertStatus(200);
        $data = $response->json('data.user');
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('name', $data);
        $this->assertArrayHasKey('email', $data);
        $this->assertArrayHasKey('email_verified_at', $data);
        $this->assertArrayHasKey('created_at', $data);
        $this->assertArrayHasKey('updated_at', $data);
    }

    public function test_update_validates_request(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->putJson('/api/profile', [
            'name' => 'Updated Name',
            'email' => $user->email,
        ]);

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
        $this->assertSame('Updated Name', $response->json('data.user.name'));
    }

    public function test_update_changes_email(): void
    {
        $user = User::factory()->create([
            'email' => 'old@example.com',
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($user)->putJson('/api/profile', [
            'name' => 'Test User',
            'email' => 'new@example.com',
        ]);

        $response->assertStatus(200);
        $this->assertSame('new@example.com', $response->json('data.user.email'));
    }

    public function test_update_clears_email_verified_at_when_email_changes(): void
    {
        $user = User::factory()->create([
            'email' => 'old@example.com',
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($user)->putJson('/api/profile', [
            'name' => 'Test User',
            'email' => 'new@example.com',
        ]);

        $response->assertStatus(200);
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

        $response = $this->actingAs($user)->putJson('/api/profile', [
            'name' => 'Updated Name',
            'email' => 'same@example.com',
        ]);

        $response->assertStatus(200);
        $user->refresh();
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_destroy_requires_password(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->deleteJson('/api/profile', []);

        $response->assertStatus(422);
    }

    public function test_destroy_validates_current_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('correct_password'),
        ]);

        $response = $this->actingAs($user)->deleteJson('/api/profile', [
            'password' => 'wrong_password',
        ]);

        $response->assertStatus(422);
    }

    public function test_destroy_deletes_user_items(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('correct_password'),
        ]);

        $item = Item::create([
            'user_id' => $user->id,
            'name' => 'Test Item',
            'quantity' => 1,
        ]);

        $this->assertDatabaseHas('thing_items', ['id' => $item->id, 'user_id' => $user->id]);

        $response = $this->actingAs($user)->deleteJson('/api/profile', [
            'password' => 'correct_password',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('thing_items', ['id' => $item->id]);
    }

    public function test_destroy_deletes_user(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('correct_password'),
        ]);
        $userId = $user->id;

        $response = $this->actingAs($user)->deleteJson('/api/profile', [
            'password' => 'correct_password',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('users', ['id' => $userId]);
    }

    public function test_destroy_runs_in_transaction(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('correct_password'),
        ]);

        $response = $this->actingAs($user)->deleteJson('/api/profile', [
            'password' => 'correct_password',
        ]);

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
    }

    public function test_update_does_not_clear_verified_at_when_name_only_changes(): void
    {
        $verifiedAt = now();
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'email_verified_at' => $verifiedAt,
        ]);

        $response = $this->actingAs($user)->putJson('/api/profile', [
            'name' => 'New Name',
            'email' => 'test@example.com',
        ]);

        $response->assertStatus(200);
        $user->refresh();
        $this->assertEquals($verifiedAt->toDateTimeString(), $user->email_verified_at->toDateTimeString());
    }
}
