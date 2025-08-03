<?php

namespace Tests\Feature\Controllers\Thing;

use App\Models\Thing\Item;
use App\Models\Thing\ItemCategory;
use App\Models\Thing\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ItemControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private ItemCategory $category;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        
        $this->user = User::factory()->create();
        $this->category = ItemCategory::factory()->create();
        
        Sanctum::actingAs($this->user);
    }

    public function test_index_returns_paginated_items()
    {
        Item::factory()->count(15)->create([
            'user_id' => $this->user->id,
            'is_public' => true,
        ]);

        $response = $this->getJson('/api/things/items');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'description',
                    'status',
                    'created_at',
                    'updated_at',
                ]
            ],
            'links',
            'meta',
        ]);
    }

    public function test_index_only_shows_public_items_for_guest()
    {
        // 创建公开和私有物品
        Item::factory()->create(['is_public' => true]);
        Item::factory()->create(['is_public' => false, 'user_id' => $this->user->id]);

        // 以访客身份访问
        auth()->logout();
        
        $response = $this->getJson('/api/things/items');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_index_shows_user_own_items_and_public_items()
    {
        // 创建不同用户的物品
        Item::factory()->create(['is_public' => true]);
        Item::factory()->create(['is_public' => false, 'user_id' => $this->user->id]);
        Item::factory()->create(['is_public' => false, 'user_id' => User::factory()->create()->id]);

        $response = $this->getJson('/api/things/items');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data')); // 公开物品 + 用户自己的私有物品
    }

    public function test_store_creates_new_item()
    {
        $itemData = [
            'name' => 'Test Item',
            'description' => 'Test Description',
            'status' => 'active',
            'category_id' => $this->category->id,
            'is_public' => true,
        ];

        $response = $this->postJson('/api/things/items', $itemData);

        $response->assertStatus(201);
        $response->assertJson([
            'name' => 'Test Item',
            'description' => 'Test Description',
            'status' => 'active',
            'user_id' => $this->user->id,
        ]);

        $this->assertDatabaseHas('thing_items', [
            'name' => 'Test Item',
            'user_id' => $this->user->id,
        ]);
    }

    public function test_store_with_images()
    {
        $images = [
            UploadedFile::fake()->image('test1.jpg'),
            UploadedFile::fake()->image('test2.jpg'),
        ];

        $itemData = [
            'name' => 'Test Item with Images',
            'description' => 'Test Description',
            'status' => 'active',
            'category_id' => $this->category->id,
            'images' => $images,
        ];

        $response = $this->postJson('/api/things/items', $itemData);

        $response->assertStatus(201);
        
        $item = Item::where('name', 'Test Item with Images')->first();
        $this->assertNotNull($item);
        $this->assertCount(2, $item->images);
    }

    public function test_store_with_tags()
    {
        $tags = Tag::factory()->count(3)->create();
        $tagIds = $tags->pluck('id')->toArray();

        $itemData = [
            'name' => 'Test Item with Tags',
            'description' => 'Test Description',
            'status' => 'active',
            'category_id' => $this->category->id,
            'tag_ids' => $tagIds,
        ];

        $response = $this->postJson('/api/things/items', $itemData);

        $response->assertStatus(201);
        
        $item = Item::where('name', 'Test Item with Tags')->first();
        $this->assertNotNull($item);
        $this->assertCount(3, $item->tags);
    }

    public function test_show_returns_item_details()
    {
        $item = Item::factory()->create([
            'user_id' => $this->user->id,
            'is_public' => true,
        ]);

        $response = $this->getJson("/api/things/items/{$item->id}");

        $response->assertStatus(200);
        $response->assertJson([
            'id' => $item->id,
            'name' => $item->name,
        ]);
    }

    public function test_show_returns_404_for_inaccessible_item()
    {
        $otherUser = User::factory()->create();
        $item = Item::factory()->create([
            'user_id' => $otherUser->id,
            'is_public' => false,
        ]);

        $response = $this->getJson("/api/things/items/{$item->id}");

        $response->assertStatus(404);
    }

    public function test_update_modifies_item()
    {
        $item = Item::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $updateData = [
            'name' => 'Updated Item Name',
            'description' => 'Updated Description',
            'status' => 'inactive',
        ];

        $response = $this->putJson("/api/things/items/{$item->id}", $updateData);

        $response->assertStatus(200);
        $response->assertJson([
            'name' => 'Updated Item Name',
            'description' => 'Updated Description',
            'status' => 'inactive',
        ]);

        $this->assertDatabaseHas('thing_items', [
            'id' => $item->id,
            'name' => 'Updated Item Name',
        ]);
    }

    public function test_update_returns_403_for_unauthorized_user()
    {
        $otherUser = User::factory()->create();
        $item = Item::factory()->create([
            'user_id' => $otherUser->id,
        ]);

        $updateData = [
            'name' => 'Updated Item Name',
        ];

        $response = $this->putJson("/api/things/items/{$item->id}", $updateData);

        $response->assertStatus(403);
    }

    public function test_destroy_deletes_item()
    {
        $item = Item::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->deleteJson("/api/things/items/{$item->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('thing_items', ['id' => $item->id]);
    }

    public function test_destroy_returns_403_for_unauthorized_user()
    {
        $otherUser = User::factory()->create();
        $item = Item::factory()->create([
            'user_id' => $otherUser->id,
        ]);

        $response = $this->deleteJson("/api/things/items/{$item->id}");

        $response->assertStatus(403);
    }

    public function test_search_returns_filtered_results()
    {
        Item::factory()->create([
            'name' => 'Apple iPhone',
            'user_id' => $this->user->id,
        ]);
        Item::factory()->create([
            'name' => 'Samsung Galaxy',
            'user_id' => $this->user->id,
        ]);

        $response = $this->getJson('/api/things/search?search=iPhone');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertStringContainsString('iPhone', $data[0]['name']);
    }

    public function test_categories_returns_all_categories()
    {
        ItemCategory::factory()->count(5)->create();

        $response = $this->getJson('/api/things/items/categories');

        $response->assertStatus(200);
        $this->assertCount(6, $response->json()); // 5 + 1 from setUp
    }

    public function test_index_with_name_filter()
    {
        Item::factory()->create([
            'name' => 'Special Item',
            'user_id' => $this->user->id,
        ]);
        Item::factory()->create([
            'name' => 'Regular Item',
            'user_id' => $this->user->id,
        ]);

        $response = $this->getJson('/api/things/items?filter[name]=Special');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Special Item', $data[0]['name']);
    }

    public function test_index_with_status_filter()
    {
        Item::factory()->create([
            'status' => 'active',
            'user_id' => $this->user->id,
        ]);
        Item::factory()->create([
            'status' => 'inactive',
            'user_id' => $this->user->id,
        ]);

        $response = $this->getJson('/api/things/items?filter[status]=active');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('active', $data[0]['status']);
    }

    public function test_index_with_tags_filter()
    {
        $tag = Tag::factory()->create();
        $item = Item::factory()->create([
            'user_id' => $this->user->id,
        ]);
        $item->tags()->attach($tag);

        Item::factory()->create([
            'user_id' => $this->user->id,
        ]); // 没有标签的物品

        $response = $this->getJson("/api/things/items?filter[tags]={$tag->id}");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($item->id, $data[0]['id']);
    }
} 