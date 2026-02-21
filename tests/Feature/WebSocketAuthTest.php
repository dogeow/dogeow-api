<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebSocketAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_websocket_auth_middleware_requires_token(): void
    {
        $response = $this->post('/broadcasting/auth', [
            'channel_name' => 'private-chat.room.1',
        ]);

        // Broadcasting auth returns 403 for unauthenticated requests on private channels
        $response->assertStatus(403);
    }

    public function test_websocket_auth_middleware_accepts_valid_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->post('/broadcasting/auth', [
            'channel_name' => 'private-chat.room.1',
        ]);

        // Should return 200 for valid token on private channel
        $response->assertStatus(200);
    }

    public function test_websocket_auth_middleware_rejects_invalid_token(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer invalid-token',
        ])->post('/broadcasting/auth', [
            'channel_name' => 'private-chat.room.1',
        ]);

        // Broadcasting auth returns 403 for invalid tokens on private channels
        $response->assertStatus(403);
    }

    public function test_public_channels_allow_access_without_auth(): void
    {
        $response = $this->post('/broadcasting/auth', [
            'channel_name' => 'chat.room.1',
        ]);

        // Public channels should allow access without authentication
        $response->assertStatus(200);
    }

    public function test_websocket_auth_with_malformed_token(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer',
        ])->post('/broadcasting/auth', [
            'channel_name' => 'private-chat.room.1',
        ]);

        $response->assertStatus(403);
    }

    public function test_websocket_auth_without_authorization_header(): void
    {
        $response = $this->post('/broadcasting/auth', [
            'channel_name' => 'private-chat.room.1',
        ]);

        $response->assertStatus(403);
    }

    public function test_websocket_auth_with_expired_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token', ['*'], now()->subDay())->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->post('/broadcasting/auth', [
            'channel_name' => 'private-chat.room.1',
        ]);

        $response->assertStatus(403);
    }
}
