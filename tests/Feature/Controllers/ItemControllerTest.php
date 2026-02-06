<?php

namespace Tests\Feature\Controllers;

use App\Models\Thing\Item;
use App\Models\Thing\ItemCategory;
use App\Models\User;
use App\Services\File\ImageUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ItemControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    /** @test */
    public function it_can_get_items_list()
    {
        $user = User::factory()->create();
        $item = Item::factory()->create([
            'user_id' => $user->id,
            'is_public' => true,
        ]);

        $response = $this->get('/api/things/items');

        $response->assertStatus(200);
        $data = $response->json();
        
        $this->assertArrayHasKey('data', $data);
        $this->assertCount(1, $data['data']);
        $this->assertEquals($item->id, $data['data'][0]['id']);
    }

    /** @test */
    public function it_filters_public_items_for_guests()
    {
        $user = User::factory()->create();
        $publicItem = Item::factory()->create([
            'user_id' => $user->id,
            'is_public' => true,
        ]);
        $privateItem = Item::factory()->create([
            'user_id' => $user->id,
            'is_public' => false,
        ]);

        $response = $this->get('/api/things/items');

        $response->assertStatus(200);
        $data = $response->json();
        
        $this->assertCount(1, $data['data']);
        $this->assertEquals($publicItem->id, $data['data'][0]['id']);
    }

    /** @test */
    public function it_shows_own_items_for_authenticated_users()
    {
        $user = User::factory()->create();
        $ownItem = Item::factory()->create([
            'user_id' => $user->id,
            'is_public' => false,
        ]);
        $otherUser = User::factory()->create();
        $otherItem = Item::factory()->create([
            'user_id' => $otherUser->id,
            'is_public' => false,
        ]);

        $response = $this->actingAs($user)->get('/api/things/items');

        $response->assertStatus(200);
        $data = $response->json();
        
        $itemIds = array_column($data['data'], 'id');
        $this->assertContains($ownItem->id, $itemIds);
        $this->assertNotContains($otherItem->id, $itemIds);
    }

    /** @test */
    public function it_can_create_item()
    {
        $user = User::factory()->create();
        $category = ItemCategory::factory()->create();

        $itemData = [
            'name' => 'Test Item',
            'description' => 'Test Description',
            'category_id' => $category->id,
            'status' => 'active',
            'is_public' => true,
            'quantity' => 1,
        ];

        $response = $this->actingAs($user)
            ->post('/api/things/items', $itemData);

        $response->assertStatus(201);
        $response->assertJson([
            'message' => '物品创建成功'
        ]);

        $this->assertDatabaseHas('thing_items', [
            'name' => 'Test Item',
            'user_id' => $user->id,
        ]);
    }

    /** @test */
    public function it_can_show_item()
    {
        $user = User::factory()->create();
        $item = Item::factory()->create([
            'user_id' => $user->id,
            'is_public' => true,
        ]);

        $response = $this->get("/api/things/items/{$item->id}");

        $response->assertStatus(200);
        $data = $response->json();
        
        $this->assertEquals($item->id, $data['id']);
        $this->assertEquals($item->name, $data['name']);
    }

    /** @test */
    public function it_denies_access_to_private_item()
    {
        $user = User::factory()->create();
        $item = Item::factory()->create([
            'user_id' => $user->id,
            'is_public' => false,
        ]);

        $response = $this->get("/api/things/items/{$item->id}");

        $response->assertStatus(403);
        $response->assertJson([
            'message' => '无权查看此物品'
        ]);
    }

    /** @test */
    public function it_allows_owner_to_view_private_item()
    {
        $user = User::factory()->create();
        $item = Item::factory()->create([
            'user_id' => $user->id,
            'is_public' => false,
        ]);

        $response = $this->actingAs($user)
            ->get("/api/things/items/{$item->id}");

        $response->assertStatus(200);
        $data = $response->json();
        
        $this->assertEquals($item->id, $data['id']);
    }

    /** @test */
    public function it_can_update_item()
    {
        $user = User::factory()->create();
        $item = Item::factory()->create([
            'user_id' => $user->id,
        ]);

        $updateData = [
            'name' => 'Updated Item Name',
            'description' => 'Updated Description',
            'quantity' => 2,
        ];

        $response = $this->actingAs($user)
            ->put("/api/things/items/{$item->id}", $updateData);

        $response->assertStatus(200);
        $response->assertJson([
            'message' => '物品更新成功'
        ]);

        $this->assertDatabaseHas('thing_items', [
            'id' => $item->id,
            'name' => 'Updated Item Name',
        ]);
    }

    /** @test */
    public function it_denies_update_to_unauthorized_user()
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $item = Item::factory()->create([
            'user_id' => $owner->id,
        ]);

        $response = $this->actingAs($otherUser)
            ->put("/api/things/items/{$item->id}", [
                'name' => 'Unauthorized Update',
                'quantity' => 1,
            ]);

        $response->assertStatus(403);
        $response->assertJson([
            'message' => '无权更新此物品'
        ]);
    }

    /** @test */
    public function it_can_delete_item()
    {
        $user = User::factory()->create();
        $item = Item::factory()->create([
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->delete("/api/things/items/{$item->id}");

        $response->assertStatus(200);
        $response->assertJson([
            'message' => '物品删除成功'
        ]);

        $this->assertDatabaseMissing('thing_items', [
            'id' => $item->id,
        ]);
    }

    /** @test */
    public function it_denies_delete_to_unauthorized_user()
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $item = Item::factory()->create([
            'user_id' => $owner->id,
        ]);

        $response = $this->actingAs($otherUser)
            ->delete("/api/things/items/{$item->id}");

        $response->assertStatus(403);
        $response->assertJson([
            'message' => '无权删除此物品'
        ]);
    }

    /** @test */
    public function it_can_search_items()
    {
        $user = User::factory()->create();
        $item = Item::factory()->create([
            'user_id' => $user->id,
            'name' => 'Special Test Item',
            'is_public' => true,
        ]);

        $response = $this->get('/api/things/search?q=Special');

        $response->assertStatus(200);
        $data = $response->json();
        
        $this->assertEquals('Special', $data['search_term']);
        $this->assertGreaterThan(0, $data['count']);
        $this->assertCount(1, $data['results']);
    }

    /** @test */
    public function it_returns_empty_search_for_empty_query()
    {
        $response = $this->get('/api/things/search?q=');

        $response->assertStatus(200);
        $data = $response->json();
        
        $this->assertEquals('', $data['search_term']);
        $this->assertEquals(0, $data['count']);
        $this->assertEmpty($data['results']);
    }

    /** @test */
    public function it_can_get_categories()
    {
        $category = ItemCategory::factory()->create();

        $response = $this->get('/api/things/items/categories');

        $response->assertStatus(200);
        $data = $response->json();
        
        $this->assertIsArray($data);
        $this->assertCount(1, $data);
        $this->assertEquals($category->id, $data[0]['id']);
    }

    /** @test */
    public function it_filters_items_by_name()
    {
        $user = User::factory()->create();
        Item::factory()->create([
            'user_id' => $user->id,
            'name' => 'Apple iPhone',
            'is_public' => true,
        ]);
        Item::factory()->create([
            'user_id' => $user->id,
            'name' => 'Samsung Galaxy',
            'is_public' => true,
        ]);

        $response = $this->get('/api/things/items?filter[name]=Apple');

        $response->assertStatus(200);
        $data = $response->json();
        
        $this->assertCount(1, $data['data']);
        $this->assertEquals('Apple iPhone', $data['data'][0]['name']);
    }

    /** @test */
    public function it_filters_items_by_status()
    {
        $user = User::factory()->create();
        Item::factory()->create([
            'user_id' => $user->id,
            'status' => 'active',
            'is_public' => true,
        ]);
        Item::factory()->create([
            'user_id' => $user->id,
            'status' => 'inactive',
            'is_public' => true,
        ]);

        $response = $this->get('/api/things/items?filter[status]=active');

        $response->assertStatus(200);
        $data = $response->json();
        
        $this->assertCount(1, $data['data']);
        $this->assertEquals('active', $data['data'][0]['status']);
    }
} 