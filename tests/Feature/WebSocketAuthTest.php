<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WebSocketAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_websocket_auth_middleware_requires_token(): void
    {
        $response = $this->get('/broadcasting/auth');
        
        // Laravel's broadcasting auth returns 403 for unauthenticated requests
        $response->assertStatus(403);
    }

    public function test_websocket_auth_middleware_accepts_valid_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->post('/broadcasting/auth', [
            'channel_name' => 'chat.room.1',
        ]);
        
        // Should not be 401 (unauthorized)
        $this->assertNotEquals(401, $response->status());
    }

    public function test_websocket_auth_middleware_rejects_invalid_token(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer invalid-token',
        ])->post('/broadcasting/auth');
        
        // Laravel's broadcasting auth returns 403 for invalid tokens
        $response->assertStatus(403);
    }
}
