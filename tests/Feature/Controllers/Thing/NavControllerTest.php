<?php

namespace Tests\Feature\Controllers\Thing;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

class NavControllerTest extends TestCase
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
        $response = $this->getJson('/api/things/nav');

        $response->assertStatus(200)
            ->assertJson(['message' => '导航功能正在开发中']);
    }

    // ==================== Store Tests ====================

    public function test_store_returns_development_message()
    {
        $data = ['name' => 'Test Navigation'];

        $response = $this->postJson('/api/things/nav', $data);

        $response->assertStatus(200)
            ->assertJson(['message' => '导航功能正在开发中']);
    }

    // ==================== Show Tests ====================

    public function test_show_returns_development_message()
    {
        $response = $this->getJson('/api/things/nav/1');

        $response->assertStatus(200)
            ->assertJson(['message' => '导航功能正在开发中']);
    }

    // ==================== Update Tests ====================

    public function test_update_returns_development_message()
    {
        $data = ['name' => 'Updated Navigation'];

        $response = $this->putJson('/api/things/nav/1', $data);

        $response->assertStatus(200)
            ->assertJson(['message' => '导航功能正在开发中']);
    }

    // ==================== Destroy Tests ====================

    public function test_destroy_returns_development_message()
    {
        $response = $this->deleteJson('/api/things/nav/1');

        $response->assertStatus(200)
            ->assertJson(['message' => '导航功能正在开发中']);
    }

    // ==================== Categories Tests ====================

    public function test_categories_returns_development_message()
    {
        $response = $this->getJson('/api/things/nav/categories');

        $response->assertStatus(200)
            ->assertJson(['message' => '导航分类功能正在开发中']);
    }
} 