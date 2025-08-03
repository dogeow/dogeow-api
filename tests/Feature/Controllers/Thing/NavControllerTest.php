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

    public function test_index_returns_json_response()
    {
        $response = $this->getJson('/api/things/nav');

        $response->assertHeader('Content-Type', 'application/json');
    }

    public function test_index_without_authentication_returns_401()
    {
        Auth::logout();
        
        $response = $this->getJson('/api/things/nav');

        $response->assertStatus(401);
    }

    // ==================== Store Tests ====================

    public function test_store_returns_development_message()
    {
        $data = ['name' => 'Test Navigation'];

        $response = $this->postJson('/api/things/nav', $data);

        $response->assertStatus(200)
            ->assertJson(['message' => '导航功能正在开发中']);
    }

    public function test_store_with_empty_data()
    {
        $response = $this->postJson('/api/things/nav', []);

        $response->assertStatus(200)
            ->assertJson(['message' => '导航功能正在开发中']);
    }

    public function test_store_with_complex_data()
    {
        $data = [
            'name' => 'Test Navigation',
            'url' => 'https://example.com',
            'description' => 'Test description',
            'category_id' => 1,
            'sort_order' => 1,
            'is_visible' => true
        ];

        $response = $this->postJson('/api/things/nav', $data);

        $response->assertStatus(200)
            ->assertJson(['message' => '导航功能正在开发中']);
    }

    public function test_store_without_authentication_returns_401()
    {
        Auth::logout();
        
        $data = ['name' => 'Test Navigation'];
        $response = $this->postJson('/api/things/nav', $data);

        $response->assertStatus(401);
    }

    // ==================== Show Tests ====================

    public function test_show_returns_development_message()
    {
        $response = $this->getJson('/api/things/nav/1');

        $response->assertStatus(200)
            ->assertJson(['message' => '导航功能正在开发中']);
    }

    public function test_show_with_different_ids()
    {
        $testIds = [1, 999, 0, -1, 'abc'];

        foreach ($testIds as $id) {
            $response = $this->getJson("/api/things/nav/{$id}");
            
            $response->assertStatus(200)
                ->assertJson(['message' => '导航功能正在开发中']);
        }
    }

    public function test_show_without_authentication_returns_401()
    {
        Auth::logout();
        
        $response = $this->getJson('/api/things/nav/1');

        $response->assertStatus(401);
    }

    // ==================== Update Tests ====================

    public function test_update_returns_development_message()
    {
        $data = ['name' => 'Updated Navigation'];

        $response = $this->putJson('/api/things/nav/1', $data);

        $response->assertStatus(200)
            ->assertJson(['message' => '导航功能正在开发中']);
    }

    public function test_update_with_patch_method()
    {
        $data = ['name' => 'Updated Navigation'];

        $response = $this->patchJson('/api/things/nav/1', $data);

        $response->assertStatus(200)
            ->assertJson(['message' => '导航功能正在开发中']);
    }

    public function test_update_with_empty_data()
    {
        $response = $this->putJson('/api/things/nav/1', []);

        $response->assertStatus(200)
            ->assertJson(['message' => '导航功能正在开发中']);
    }

    public function test_update_with_different_ids()
    {
        $data = ['name' => 'Updated Navigation'];
        $testIds = [1, 999, 0, -1, 'abc'];

        foreach ($testIds as $id) {
            $response = $this->putJson("/api/things/nav/{$id}", $data);
            
            $response->assertStatus(200)
                ->assertJson(['message' => '导航功能正在开发中']);
        }
    }

    public function test_update_without_authentication_returns_401()
    {
        Auth::logout();
        
        $data = ['name' => 'Updated Navigation'];
        $response = $this->putJson('/api/things/nav/1', $data);

        $response->assertStatus(401);
    }

    // ==================== Destroy Tests ====================

    public function test_destroy_returns_development_message()
    {
        $response = $this->deleteJson('/api/things/nav/1');

        $response->assertStatus(200)
            ->assertJson(['message' => '导航功能正在开发中']);
    }

    public function test_destroy_with_different_ids()
    {
        $testIds = [1, 999, 0, -1, 'abc'];

        foreach ($testIds as $id) {
            $response = $this->deleteJson("/api/things/nav/{$id}");
            
            $response->assertStatus(200)
                ->assertJson(['message' => '导航功能正在开发中']);
        }
    }

    public function test_destroy_without_authentication_returns_401()
    {
        Auth::logout();
        
        $response = $this->deleteJson('/api/things/nav/1');

        $response->assertStatus(401);
    }

    // ==================== Categories Tests ====================

    public function test_categories_returns_development_message()
    {
        $response = $this->getJson('/api/things/nav/categories');

        $response->assertStatus(200)
            ->assertJson(['message' => '导航分类功能正在开发中']);
    }

    public function test_categories_returns_json_response()
    {
        $response = $this->getJson('/api/things/nav/categories');

        $response->assertHeader('Content-Type', 'application/json');
    }

    public function test_categories_without_authentication_returns_401()
    {
        Auth::logout();
        
        $response = $this->getJson('/api/things/nav/categories');

        $response->assertStatus(401);
    }

    // ==================== Edge Cases Tests ====================

    public function test_all_endpoints_with_malformed_json()
    {
        $endpoints = [
            'POST' => '/api/things/nav',
            'PUT' => '/api/things/nav/1',
            'PATCH' => '/api/things/nav/1'
        ];

        foreach ($endpoints as $method => $url) {
            $response = $this->call($method, $url, [], [], [], [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json'
            ], '{"invalid": json}');
            
            // Should still return 200 with development message
            $response->assertStatus(200);
        }
    }

    public function test_all_endpoints_with_query_parameters()
    {
        $endpoints = [
            'GET /api/things/nav' => '/api/things/nav?page=1&limit=10',
            'GET /api/things/nav/1' => '/api/things/nav/1?include=category',
            'GET /api/things/nav/categories' => '/api/things/nav/categories?sort=name'
        ];

        foreach ($endpoints as $method => $url) {
            $response = $this->getJson($url);
            
            $response->assertStatus(200);
        }
    }

    public function test_all_endpoints_with_headers()
    {
        $endpoints = [
            'GET /api/things/nav' => '/api/things/nav',
            'GET /api/things/nav/1' => '/api/things/nav/1',
            'GET /api/things/nav/categories' => '/api/things/nav/categories'
        ];

        foreach ($endpoints as $method => $url) {
            $response = $this->withHeaders([
                'Accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
                'User-Agent' => 'Test Agent'
            ])->getJson($url);
            
            $response->assertStatus(200);
        }
    }

    // ==================== Authentication Tests ====================

    public function test_authenticated_user_can_access_all_endpoints()
    {
        $endpoints = [
            'GET /api/things/nav' => ['GET', '/api/things/nav'],
            'POST /api/things/nav' => ['POST', '/api/things/nav'],
            'GET /api/things/nav/1' => ['GET', '/api/things/nav/1'],
            'PUT /api/things/nav/1' => ['PUT', '/api/things/nav/1'],
            'DELETE /api/things/nav/1' => ['DELETE', '/api/things/nav/1'],
            'GET /api/things/nav/categories' => ['GET', '/api/things/nav/categories']
        ];

        foreach ($endpoints as $name => [$method, $url]) {
            $response = $this->call($method, $url);
            $response->assertStatus(200);
        }
    }
} 