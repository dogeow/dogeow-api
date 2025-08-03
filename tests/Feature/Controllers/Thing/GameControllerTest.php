<?php

namespace Tests\Feature\Controllers\Thing;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

class GameControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Auth::login($this->user);
    }

    // ==================== Index Tests ====================

    public function test_index_returns_development_message()
    {
        $response = $this->getJson('/api/things/games');

        $response->assertStatus(200)
            ->assertJson(['message' => '游戏功能正在开发中']);
    }

    // ==================== Store Tests ====================

    public function test_store_returns_development_message()
    {
        $data = ['name' => 'Test Game'];

        $response = $this->postJson('/api/things/games', $data);

        $response->assertStatus(200)
            ->assertJson(['message' => '游戏功能正在开发中']);
    }

    // ==================== Show Tests ====================

    public function test_show_returns_development_message()
    {
        $response = $this->getJson('/api/things/games/1');

        $response->assertStatus(200)
            ->assertJson(['message' => '游戏功能正在开发中']);
    }

    // ==================== Update Tests ====================

    public function test_update_returns_development_message()
    {
        $data = ['name' => 'Updated Game'];

        $response = $this->putJson('/api/things/games/1', $data);

        $response->assertStatus(200)
            ->assertJson(['message' => '游戏功能正在开发中']);
    }

    // ==================== Destroy Tests ====================

    public function test_destroy_returns_development_message()
    {
        $response = $this->deleteJson('/api/things/games/1');

        $response->assertStatus(200)
            ->assertJson(['message' => '游戏功能正在开发中']);
    }

    // ==================== Play Tests ====================

    public function test_play_returns_development_message()
    {
        $response = $this->getJson('/api/things/games/1/play');

        $response->assertStatus(200)
            ->assertJson(['message' => '游戏功能正在开发中']);
    }
} 