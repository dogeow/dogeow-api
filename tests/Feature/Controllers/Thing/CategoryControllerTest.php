<?php

namespace Tests\Feature\Controllers\Thing;

use Tests\TestCase;
use App\Models\Thing\ItemCategory;
use App\Models\Thing\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

class CategoryControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private User $otherUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->otherUser = User::factory()->create();
        Auth::login($this->user);
    }

    // ==================== Index Tests ====================

    public function test_index_returns_user_categories()
    {
        $userCategory = ItemCategory::factory()->create(['user_id' => $this->user->id]);
        $otherUserCategory = ItemCategory::factory()->create(['user_id' => $this->otherUser->id]);

        $response = $this->getJson('/api/things/categories');

        $response->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonFragment(['id' => $userCategory->id])
            ->assertJsonMissing(['id' => $otherUserCategory->id]);
    }

    public function test_index_includes_parent_and_children_relationships()
    {
        $parentCategory = ItemCategory::factory()->create([
            'user_id' => $this->user->id,
            'parent_id' => null
        ]);
        $childCategory = ItemCategory::factory()->create([
            'user_id' => $this->user->id,
            'parent_id' => $parentCategory->id
        ]);

        $response = $this->getJson('/api/things/categories');

        $response->assertStatus(200)
            ->assertJsonStructure([
                '*' => [
                    'id',
                    'name',
                    'parent',
                    'children',
                    'items_count'
                ]
            ]);
    }

    // ==================== Store Tests ====================

    public function test_store_creates_new_category()
    {
        $data = ['name' => 'Test Category'];

        $response = $this->postJson('/api/things/categories', $data);

        $response->assertStatus(201)
            ->assertJson([
                'message' => '分类创建成功',
                'category' => [
                    'name' => 'Test Category',
                    'user_id' => $this->user->id,
                ]
            ]);

        $this->assertDatabaseHas('thing_item_categories', [
            'name' => 'Test Category',
            'user_id' => $this->user->id,
        ]);
    }

    public function test_store_creates_category_with_parent()
    {
        $parentCategory = ItemCategory::factory()->create([
            'user_id' => $this->user->id,
            'parent_id' => null
        ]);

        $data = [
            'name' => 'Child Category',
            'parent_id' => $parentCategory->id
        ];

        $response = $this->postJson('/api/things/categories', $data);

        $response->assertStatus(201)
            ->assertJson([
                'message' => '分类创建成功',
                'category' => [
                    'name' => 'Child Category',
                    'parent_id' => $parentCategory->id,
                    'user_id' => $this->user->id,
                ]
            ]);
    }

    public function test_store_returns_422_for_invalid_parent()
    {
        $data = [
            'name' => 'Test Category',
            'parent_id' => 999
        ];

        $response = $this->postJson('/api/things/categories', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['parent_id']);
    }

    public function test_store_returns_400_for_other_user_parent()
    {
        $otherUserParent = ItemCategory::factory()->create(['user_id' => $this->otherUser->id]);

        $data = [
            'name' => 'Test Category',
            'parent_id' => $otherUserParent->id
        ];

        $response = $this->postJson('/api/things/categories', $data);

        $response->assertStatus(400)
            ->assertJson(['message' => '指定的父分类不存在或无权访问']);
    }

    public function test_store_returns_400_for_third_level_category()
    {
        $parentCategory = ItemCategory::factory()->create([
            'user_id' => $this->user->id,
            'parent_id' => null
        ]);
        $childCategory = ItemCategory::factory()->create([
            'user_id' => $this->user->id,
            'parent_id' => $parentCategory->id
        ]);

        $data = [
            'name' => 'Third Level Category',
            'parent_id' => $childCategory->id
        ];

        $response = $this->postJson('/api/things/categories', $data);

        $response->assertStatus(400)
            ->assertJson(['message' => '不能在子分类下创建分类']);
    }

    public function test_store_validation_fails_without_name()
    {
        $response = $this->postJson('/api/things/categories', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_store_validation_fails_with_long_name()
    {
        $data = ['name' => str_repeat('a', 256)];

        $response = $this->postJson('/api/things/categories', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    // ==================== Show Tests ====================

    public function test_show_returns_category()
    {
        $category = ItemCategory::factory()->create(['user_id' => $this->user->id]);

        $response = $this->getJson("/api/things/categories/{$category->id}");

        $response->assertStatus(200)
            ->assertJson([
                'id' => $category->id,
                'name' => $category->name,
            ]);
    }

    public function test_show_returns_403_for_other_user_category()
    {
        $category = ItemCategory::factory()->create(['user_id' => $this->otherUser->id]);

        $response = $this->getJson("/api/things/categories/{$category->id}");

        $response->assertStatus(403)
            ->assertJson(['message' => '无权查看此分类']);
    }

    public function test_show_includes_items_relationship()
    {
        $category = ItemCategory::factory()->create(['user_id' => $this->user->id]);
        $item = Item::factory()->create([
            'user_id' => $this->user->id,
            'category_id' => $category->id
        ]);

        $response = $this->getJson("/api/things/categories/{$category->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'name',
                'items' => [
                    '*' => [
                        'id',
                        'name'
                    ]
                ]
            ]);
    }

    // ==================== Update Tests ====================

    public function test_update_modifies_category()
    {
        $category = ItemCategory::factory()->create(['user_id' => $this->user->id]);
        $data = ['name' => 'Updated Category'];

        $response = $this->putJson("/api/things/categories/{$category->id}", $data);

        $response->assertStatus(200)
            ->assertJson([
                'message' => '分类更新成功',
                'category' => [
                    'id' => $category->id,
                    'name' => 'Updated Category',
                ]
            ]);

        $this->assertDatabaseHas('thing_item_categories', [
            'id' => $category->id,
            'name' => 'Updated Category',
        ]);
    }

    public function test_update_returns_403_for_other_user_category()
    {
        $category = ItemCategory::factory()->create(['user_id' => $this->otherUser->id]);
        $data = ['name' => 'Updated Category'];

        $response = $this->putJson("/api/things/categories/{$category->id}", $data);

        $response->assertStatus(403)
            ->assertJson(['message' => '无权更新此分类']);
    }

    public function test_update_validation_fails_with_long_name()
    {
        $category = ItemCategory::factory()->create(['user_id' => $this->user->id]);
        $data = ['name' => str_repeat('a', 256)];

        $response = $this->putJson("/api/things/categories/{$category->id}", $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    // ==================== Destroy Tests ====================

    public function test_destroy_deletes_category()
    {
        $category = ItemCategory::factory()->create(['user_id' => $this->user->id]);

        $response = $this->deleteJson("/api/things/categories/{$category->id}");

        $response->assertStatus(200)
            ->assertJson(['message' => '分类删除成功']);

        $this->assertDatabaseMissing('thing_item_categories', [
            'id' => $category->id,
        ]);
    }

    public function test_destroy_returns_403_for_other_user_category()
    {
        $category = ItemCategory::factory()->create(['user_id' => $this->otherUser->id]);

        $response = $this->deleteJson("/api/things/categories/{$category->id}");

        $response->assertStatus(403)
            ->assertJson(['message' => '无权删除此分类']);
    }

    public function test_destroy_returns_400_when_category_has_items()
    {
        $category = ItemCategory::factory()->create(['user_id' => $this->user->id]);
        $item = Item::factory()->create([
            'user_id' => $this->user->id,
            'category_id' => $category->id
        ]);

        $response = $this->deleteJson("/api/things/categories/{$category->id}");

        $response->assertStatus(400)
            ->assertJson(['message' => '无法删除已有物品的分类']);
    }

    public function test_destroy_returns_400_when_category_has_children()
    {
        $parentCategory = ItemCategory::factory()->create([
            'user_id' => $this->user->id,
            'parent_id' => null
        ]);
        $childCategory = ItemCategory::factory()->create([
            'user_id' => $this->user->id,
            'parent_id' => $parentCategory->id
        ]);

        $response = $this->deleteJson("/api/things/categories/{$parentCategory->id}");

        $response->assertStatus(400)
            ->assertJson(['message' => '无法删除有子分类的分类']);
    }
} 