<?php

namespace Tests\Feature\Controllers\Thing;

use Tests\TestCase;
use App\Models\Thing\Tag;
use App\Models\Thing\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

class TagControllerTest extends TestCase
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

    public function test_index_returns_user_tags()
    {
        $userTag = Tag::factory()->create(['user_id' => $this->user->id]);
        $otherUserTag = Tag::factory()->create(['user_id' => $this->otherUser->id]);

        $response = $this->getJson('/api/things/tags');

        $response->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonFragment(['id' => $userTag->id])
            ->assertJsonMissing(['id' => $otherUserTag->id]);
    }

    public function test_index_includes_items_count()
    {
        $tag = Tag::factory()->create(['user_id' => $this->user->id]);
        $item = Item::factory()->create(['user_id' => $this->user->id]);
        $item->tags()->attach($tag->id);

        $response = $this->getJson('/api/things/tags');

        $response->assertStatus(200)
            ->assertJsonStructure([
                '*' => [
                    'id',
                    'name',
                    'color',
                    'items_count'
                ]
            ])
            ->assertJsonFragment(['items_count' => 1]);
    }

    public function test_index_orders_by_created_at_desc()
    {
        $tag1 = Tag::factory()->create([
            'user_id' => $this->user->id,
            'created_at' => now()->subDay()
        ]);
        $tag2 = Tag::factory()->create([
            'user_id' => $this->user->id,
            'created_at' => now()
        ]);

        $response = $this->getJson('/api/things/tags');

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertEquals($tag2->id, $data[0]['id']);
        $this->assertEquals($tag1->id, $data[1]['id']);
    }

    // ==================== Store Tests ====================

    public function test_store_creates_new_tag()
    {
        $data = ['name' => 'Test Tag'];

        $response = $this->postJson('/api/things/tags', $data);

        $response->assertStatus(201)
            ->assertJson([
                'name' => 'Test Tag',
                'user_id' => $this->user->id,
                'color' => '#3b82f6'
            ]);

        $this->assertDatabaseHas('thing_tags', [
            'name' => 'Test Tag',
            'user_id' => $this->user->id,
            'color' => '#3b82f6'
        ]);
    }

    public function test_store_creates_tag_with_custom_color()
    {
        $data = [
            'name' => 'Test Tag',
            'color' => '#ff0000'
        ];

        $response = $this->postJson('/api/things/tags', $data);

        $response->assertStatus(201)
            ->assertJson([
                'name' => 'Test Tag',
                'color' => '#ff0000'
            ]);

        $this->assertDatabaseHas('thing_tags', [
            'name' => 'Test Tag',
            'color' => '#ff0000'
        ]);
    }

    public function test_store_validation_fails_without_name()
    {
        $response = $this->postJson('/api/things/tags', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_store_validation_fails_with_long_name()
    {
        $data = ['name' => str_repeat('a', 256)];

        $response = $this->postJson('/api/things/tags', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_store_validation_fails_with_invalid_color()
    {
        $data = [
            'name' => 'Test Tag',
            'color' => 'invalid-color'
        ];

        $response = $this->postJson('/api/things/tags', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['color']);
    }

    // ==================== Show Tests ====================

    public function test_show_returns_tag()
    {
        $tag = Tag::factory()->create(['user_id' => $this->user->id]);

        $response = $this->getJson("/api/things/tags/{$tag->id}");

        $response->assertStatus(200)
            ->assertJson([
                'id' => $tag->id,
                'name' => $tag->name,
                'color' => $tag->color,
            ]);
    }

    public function test_show_returns_404_for_other_user_tag()
    {
        $tag = Tag::factory()->create(['user_id' => $this->otherUser->id]);

        $response = $this->getJson("/api/things/tags/{$tag->id}");

        $response->assertStatus(404);
    }

    public function test_show_returns_404_for_nonexistent_tag()
    {
        $response = $this->getJson("/api/things/tags/999");

        $response->assertStatus(404);
    }

    // ==================== Update Tests ====================

    public function test_update_modifies_tag()
    {
        $tag = Tag::factory()->create(['user_id' => $this->user->id]);
        $data = [
            'name' => 'Updated Tag',
            'color' => '#00ff00'
        ];

        $response = $this->putJson("/api/things/tags/{$tag->id}", $data);

        $response->assertStatus(200)
            ->assertJson([
                'id' => $tag->id,
                'name' => 'Updated Tag',
                'color' => '#00ff00'
            ]);

        $this->assertDatabaseHas('thing_tags', [
            'id' => $tag->id,
            'name' => 'Updated Tag',
            'color' => '#00ff00'
        ]);
    }

    public function test_update_returns_404_for_other_user_tag()
    {
        $tag = Tag::factory()->create(['user_id' => $this->otherUser->id]);
        $data = ['name' => 'Updated Tag'];

        $response = $this->putJson("/api/things/tags/{$tag->id}", $data);

        $response->assertStatus(404);
    }

    public function test_update_validation_fails_with_long_name()
    {
        $tag = Tag::factory()->create(['user_id' => $this->user->id]);
        $data = ['name' => str_repeat('a', 256)];

        $response = $this->putJson("/api/things/tags/{$tag->id}", $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_update_validation_fails_with_invalid_color()
    {
        $tag = Tag::factory()->create(['user_id' => $this->user->id]);
        $data = [
            'name' => 'Test Tag',
            'color' => 'invalid-color'
        ];

        $response = $this->putJson("/api/things/tags/{$tag->id}", $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['color']);
    }

    // ==================== Destroy Tests ====================

    public function test_destroy_deletes_tag()
    {
        $tag = Tag::factory()->create(['user_id' => $this->user->id]);

        $response = $this->deleteJson("/api/things/tags/{$tag->id}");

        $response->assertStatus(204);

        // 由于使用了SoftDeletes，数据仍然存在但被标记为已删除
        $this->assertDatabaseHas('thing_tags', [
            'id' => $tag->id,
            'deleted_at' => now()->toDateTimeString()
        ]);
    }

    public function test_destroy_returns_404_for_other_user_tag()
    {
        $tag = Tag::factory()->create(['user_id' => $this->otherUser->id]);

        $response = $this->deleteJson("/api/things/tags/{$tag->id}");

        $response->assertStatus(404);
    }

    public function test_destroy_detaches_items_before_deletion()
    {
        $tag = Tag::factory()->create(['user_id' => $this->user->id]);
        $item = Item::factory()->create(['user_id' => $this->user->id]);
        $item->tags()->attach($tag->id);

        $response = $this->deleteJson("/api/things/tags/{$tag->id}");

        $response->assertStatus(204);

        // 由于使用了SoftDeletes，数据仍然存在但被标记为已删除
        $this->assertDatabaseHas('thing_tags', [
            'id' => $tag->id,
            'deleted_at' => now()->toDateTimeString()
        ]);

        // 检查关联关系已被删除
        $this->assertDatabaseMissing('thing_item_tag', [
            'thing_tag_id' => $tag->id,
            'item_id' => $item->id,
        ]);
    }

    public function test_destroy_returns_404_for_nonexistent_tag()
    {
        $response = $this->deleteJson("/api/things/tags/999");

        $response->assertStatus(404);
    }
} 