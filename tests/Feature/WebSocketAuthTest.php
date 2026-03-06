<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebSocketAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_private_channel_requires_token(): void
    {
        $response = $this->postJson('/broadcasting/auth', [
            'channel_name' => 'private-chat.room.1',
        ]);

        // 私有频道未认证返回 401
        $response->assertStatus(401);
        $response->assertJson(['error' => 'Unauthorized']);
    }

    public function test_private_channel_accepts_valid_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/broadcasting/auth', [
            'channel_name' => 'private-chat.room.1',
            'socket_id' => '123.456',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['auth' => 'success']);
    }

    public function test_private_channel_rejects_invalid_token(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer invalid-token',
        ])->postJson('/broadcasting/auth', [
            'channel_name' => 'private-chat.room.1',
        ]);

        $response->assertStatus(401);
        $response->assertJson(['error' => 'Unauthorized']);
    }

    public function test_public_channels_allow_without_auth(): void
    {
        // 公共频道（不以 private- 或 presence- 开头）允许无需认证访问
        $response = $this->postJson('/broadcasting/auth', [
            'channel_name' => 'log-updates',
            'socket_id' => '123.456',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['auth' => 'public']);
    }

    public function test_private_channel_with_malformed_token(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer',
        ])->postJson('/broadcasting/auth', [
            'channel_name' => 'private-chat.room.1',
        ]);

        $response->assertStatus(401);
        $response->assertJson(['error' => 'Unauthorized']);
    }

    public function test_private_channel_without_authorization_header(): void
    {
        $response = $this->postJson('/broadcasting/auth', [
            'channel_name' => 'private-chat.room.1',
        ]);

        $response->assertStatus(401);
        $response->assertJson(['error' => 'Unauthorized']);
    }

    public function test_private_channel_with_expired_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token', ['*'], now()->subDay())->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/broadcasting/auth', [
            'channel_name' => 'private-chat.room.1',
        ]);

        $response->assertStatus(401);
        $response->assertJson(['error' => 'Unauthorized']);
    }

    public function test_presence_channel_requires_auth(): void
    {
        $response = $this->postJson('/broadcasting/auth', [
            'channel_name' => 'presence-chat.room.1',
        ]);

        $response->assertStatus(401);
        $response->assertJson(['error' => 'Unauthorized']);
    }

    public function test_user_private_channel_requires_matching_user_id(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        // 尝试订阅其他用户的私有频道
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/broadcasting/auth', [
            'channel_name' => 'private-user.' . $otherUser->id . '.notifications',
            'socket_id' => '123.456',
        ]);

        $response->assertStatus(403);
        $response->assertJson(['error' => 'Forbidden']);
    }

    public function test_user_private_channel_allows_own_user_id(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        // 订阅自己的私有频道
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/broadcasting/auth', [
            'channel_name' => 'private-user.' . $user->id . '.notifications',
            'socket_id' => '123.456',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['auth' => 'success']);
    }
}
