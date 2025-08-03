<?php

namespace Tests\Feature\Controllers\Nav;

use Tests\TestCase;
use App\Models\Nav\Category;
use App\Models\Nav\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

class CategoryControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    /**
     * 测试获取所有导航分类（默认行为）
     */
    public function test_index_returns_visible_categories_with_items()
    {
        // 创建可见的分类
        $visibleCategory = Category::factory()->visible()->create();
        $hiddenCategory = Category::factory()->hidden()->create();
        
        // 为可见分类创建导航项
        Item::factory()->count(3)->create(['nav_category_id' => $visibleCategory->id]);

        $response = $this->getJson('/api/nav/categories');

        $response->assertStatus(200);
        
        $categories = $response->json();
        $this->assertCount(1, $categories);
        $this->assertEquals($visibleCategory->id, $categories[0]['id']);
        $this->assertNotContains($hiddenCategory->id, array_column($categories, 'id'));
    }

    /**
     * 测试获取所有导航分类（管理员视图）
     */
    public function test_index_with_show_all_returns_all_categories()
    {
        $visibleCategory = Category::factory()->visible()->create();
        $hiddenCategory = Category::factory()->hidden()->create();

        $response = $this->getJson('/api/nav/categories?show_all=1');

        $response->assertStatus(200)
            ->assertJsonCount(2)
            ->assertJsonFragment(['id' => $visibleCategory->id])
            ->assertJsonFragment(['id' => $hiddenCategory->id]);
    }

    /**
     * 测试按名称筛选导航项
     */
    public function test_index_filters_items_by_name()
    {
        $category = Category::factory()->visible()->create();
        
        // 创建匹配的导航项
        Item::factory()->create([
            'nav_category_id' => $category->id,
            'name' => 'Test Item'
        ]);
        
        // 创建不匹配的导航项
        Item::factory()->create([
            'nav_category_id' => $category->id,
            'name' => 'Other Item'
        ]);

        $response = $this->getJson('/api/nav/categories?filter[name]=Test');

        $response->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonFragment(['id' => $category->id]);
    }

    /**
     * 测试按名称筛选时只返回有匹配项的分类
     */
    public function test_index_filters_categories_with_matching_items()
    {
        $categoryWithMatchingItem = Category::factory()->visible()->create();
        $categoryWithoutMatchingItem = Category::factory()->visible()->create();
        
        Item::factory()->create([
            'nav_category_id' => $categoryWithMatchingItem->id,
            'name' => 'Test Item'
        ]);
        
        Item::factory()->create([
            'nav_category_id' => $categoryWithoutMatchingItem->id,
            'name' => 'Other Item'
        ]);

        $response = $this->getJson('/api/nav/categories?filter[name]=Test');

        $response->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonFragment(['id' => $categoryWithMatchingItem->id])
            ->assertJsonMissing(['id' => $categoryWithoutMatchingItem->id]);
    }

    /**
     * 测试获取所有分类（管理员）
     */
    public function test_all_returns_all_categories_with_item_count()
    {
        $category1 = Category::factory()->create();
        $category2 = Category::factory()->create();
        
        Item::factory()->count(3)->create(['nav_category_id' => $category1->id]);
        Item::factory()->count(1)->create(['nav_category_id' => $category2->id]);

        $response = $this->getJson('/api/nav/categories/all');

        $response->assertStatus(200)
            ->assertJsonCount(2)
            ->assertJsonFragment(['id' => $category1->id, 'items_count' => 3])
            ->assertJsonFragment(['id' => $category2->id, 'items_count' => 1]);
    }

    /**
     * 测试创建导航分类
     */
    public function test_store_creates_new_category()
    {
        $this->actingAs($this->user);
        
        $data = [
            'name' => 'Test Category',
            'icon' => 'test-icon',
            'description' => 'Test description',
            'sort_order' => 5,
            'is_visible' => true,
        ];

        $response = $this->postJson('/api/nav/categories', $data);

        $response->assertStatus(201)
            ->assertJson([
                'message' => '分类创建成功',
                'category' => [
                    'name' => 'Test Category',
                    'icon' => 'test-icon',
                    'description' => 'Test description',
                    'sort_order' => 5,
                    'is_visible' => true,
                ]
            ]);

        $this->assertDatabaseHas('nav_categories', [
            'name' => 'Test Category',
            'icon' => 'test-icon',
            'description' => 'Test description',
            'sort_order' => 5,
            'is_visible' => true,
        ]);
    }

    /**
     * 测试创建分类时的验证失败
     */
    public function test_store_validation_fails_without_name()
    {
        $this->actingAs($this->user);
        
        $data = [
            'icon' => 'test-icon',
            'description' => 'Test description',
        ];

        $response = $this->postJson('/api/nav/categories', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    /**
     * 测试创建分类时的验证失败 - 名称过长
     */
    public function test_store_validation_fails_with_long_name()
    {
        $this->actingAs($this->user);
        
        $data = [
            'name' => str_repeat('a', 51), // 超过50字符限制
            'icon' => 'test-icon',
        ];

        $response = $this->postJson('/api/nav/categories', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    /**
     * 测试显示指定导航分类
     */
    public function test_show_returns_category_with_items()
    {
        $category = Category::factory()->create();
        Item::factory()->count(3)->create(['nav_category_id' => $category->id]);

        $response = $this->getJson("/api/nav/categories/{$category->id}");

        $response->assertStatus(200)
            ->assertJson([
                'id' => $category->id,
                'name' => $category->name,
            ])
            ->assertJsonCount(3, 'items');
    }

    /**
     * 测试显示不存在的分类
     */
    public function test_show_returns_404_for_nonexistent_category()
    {
        $response = $this->getJson('/api/nav/categories/999');

        $response->assertStatus(404);
    }

    /**
     * 测试更新导航分类
     */
    public function test_update_modifies_category()
    {
        $this->actingAs($this->user);
        
        $category = Category::factory()->create();
        
        $updateData = [
            'name' => 'Updated Category',
            'icon' => 'updated-icon',
            'description' => 'Updated description',
            'sort_order' => 10,
            'is_visible' => false,
        ];

        $response = $this->putJson("/api/nav/categories/{$category->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'message' => '分类更新成功',
                'category' => [
                    'id' => $category->id,
                    'name' => 'Updated Category',
                    'icon' => 'updated-icon',
                    'description' => 'Updated description',
                    'sort_order' => 10,
                    'is_visible' => false,
                ]
            ]);

        $this->assertDatabaseHas('nav_categories', [
            'id' => $category->id,
            'name' => 'Updated Category',
            'icon' => 'updated-icon',
            'description' => 'Updated description',
            'sort_order' => 10,
            'is_visible' => false,
        ]);
    }

    /**
     * 测试部分更新分类
     */
    public function test_update_partial_fields()
    {
        $this->actingAs($this->user);
        
        $category = Category::factory()->create();
        
        $updateData = [
            'name' => 'Updated Name',
        ];

        $response = $this->putJson("/api/nav/categories/{$category->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'category' => [
                    'id' => $category->id,
                    'name' => 'Updated Name',
                ]
            ]);

        $this->assertDatabaseHas('nav_categories', [
            'id' => $category->id,
            'name' => 'Updated Name',
        ]);
    }

    /**
     * 测试更新分类时的验证失败
     */
    public function test_update_validation_fails_with_long_name()
    {
        $this->actingAs($this->user);
        
        $category = Category::factory()->create();
        
        $updateData = [
            'name' => str_repeat('a', 51),
        ];

        $response = $this->putJson("/api/nav/categories/{$category->id}", $updateData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    /**
     * 测试删除导航分类
     */
    public function test_destroy_deletes_category()
    {
        $this->actingAs($this->user);
        
        $category = Category::factory()->create();

        $response = $this->deleteJson("/api/nav/categories/{$category->id}");

        $response->assertStatus(200)
            ->assertJson([
                'message' => '分类删除成功'
            ]);

        $this->assertSoftDeleted('nav_categories', ['id' => $category->id]);
    }

    /**
     * 测试删除有导航项的分类失败
     */
    public function test_destroy_fails_when_category_has_items()
    {
        $this->actingAs($this->user);
        
        $category = Category::factory()->create();
        Item::factory()->create(['nav_category_id' => $category->id]);

        $response = $this->deleteJson("/api/nav/categories/{$category->id}");

        $response->assertStatus(422)
            ->assertJson([
                'message' => '该分类下存在导航项，无法删除'
            ]);

        $this->assertDatabaseHas('nav_categories', ['id' => $category->id]);
    }

    /**
     * 测试删除不存在的分类
     */
    public function test_destroy_returns_404_for_nonexistent_category()
    {
        $this->actingAs($this->user);
        
        $response = $this->deleteJson('/api/nav/categories/999');

        $response->assertStatus(404);
    }

    /**
     * 测试分类按排序顺序返回
     */
    public function test_categories_are_ordered_by_sort_order()
    {
        $category3 = Category::factory()->create(['sort_order' => 3]);
        $category1 = Category::factory()->create(['sort_order' => 1]);
        $category2 = Category::factory()->create(['sort_order' => 2]);

        $response = $this->getJson('/api/nav/categories?show_all=1');

        $response->assertStatus(200);
        
        $categories = $response->json();
        $this->assertEquals($category1->id, $categories[0]['id']);
        $this->assertEquals($category2->id, $categories[1]['id']);
        $this->assertEquals($category3->id, $categories[2]['id']);
    }
} 