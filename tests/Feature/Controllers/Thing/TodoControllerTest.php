<?php

namespace Tests\Feature\Controllers\Thing;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

class TodoControllerTest extends TestCase
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
        $response = $this->getJson('/api/things/todos');

        $response->assertStatus(200)
            ->assertJson(['message' => '待办事项功能正在开发中']);
    }

    // ==================== Store Tests ====================

    public function test_store_returns_development_message()
    {
        $data = [
            'title' => 'Test Todo',
            'description' => 'Test Description'
        ];

        $response = $this->postJson('/api/things/todos', $data);

        $response->assertStatus(200)
            ->assertJson(['message' => '待办事项功能正在开发中']);
    }

    // ==================== Show Tests ====================

    public function test_show_returns_development_message()
    {
        $response = $this->getJson('/api/things/todos/1');

        $response->assertStatus(200)
            ->assertJson(['message' => '待办事项功能正在开发中']);
    }

    // ==================== Update Tests ====================

    public function test_update_returns_development_message()
    {
        $data = [
            'title' => 'Updated Todo',
            'description' => 'Updated Description'
        ];

        $response = $this->putJson('/api/things/todos/1', $data);

        $response->assertStatus(200)
            ->assertJson(['message' => '待办事项功能正在开发中']);
    }

    // ==================== Destroy Tests ====================

    public function test_destroy_returns_development_message()
    {
        $response = $this->deleteJson('/api/things/todos/1');

        $response->assertStatus(200)
            ->assertJson(['message' => '待办事项功能正在开发中']);
    }
} 