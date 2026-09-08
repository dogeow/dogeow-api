<?php

namespace Tests\Feature;

use App\Models\Cloud\File;
use App\Models\Note\Note;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ProjectIntegrityTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        Queue::fake();
    }

    public function test_invalid_file_sorting_returns_validation_error(): void
    {
        $this->getJson('/api/cloud/files?sort_by=unknown_column')->assertUnprocessable()->assertJsonValidationErrors('sort_by');
        $this->getJson('/api/cloud/files?sort_direction=invalid')->assertUnprocessable()->assertJsonValidationErrors('sort_direction');
        $this->getJson('/api/cloud/files?search[]=invalid')->assertUnprocessable()->assertJsonValidationErrors('search');
    }

    public function test_root_listing_accepts_empty_optional_filters(): void
    {
        File::factory()->folder()->create(['user_id' => $this->user->id]);
        $this->getJson('/api/cloud/files?parent_id=&search=&type=')->assertOk()->assertJsonCount(1);
    }

    public function test_renaming_a_file_preserves_its_description(): void
    {
        $file = File::factory()->folder()->create(['user_id' => $this->user->id, 'description' => '保留说明']);
        $this->patchJson('/api/cloud/files/' . $file->id, ['name' => '新名称'])->assertOk();
        $this->assertSame('新名称', $file->fresh()->name);
        $this->assertSame('保留说明', $file->fresh()->description);
        $this->patchJson('/api/cloud/files/' . $file->id, ['name' => '新名称', 'description' => null])->assertOk();
        $this->assertNull($file->fresh()->description);
    }

    public function test_mixed_owner_move_is_rejected_without_moving_any_files(): void
    {
        $owned = File::factory()->folder()->create(['user_id' => $this->user->id]);
        $foreign = File::factory()->folder()->create(['user_id' => User::factory()->create()->id]);
        $destination = File::factory()->folder()->create(['user_id' => $this->user->id]);
        $this->postJson('/api/cloud/files/move', [
            'file_ids' => [$owned->id, $foreign->id], 'target_folder_id' => $destination->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('file_ids.1');
        $this->assertNull($owned->fresh()->parent_id);
        $this->assertNull($foreign->fresh()->parent_id);
    }

    public function test_move_rejects_empty_duplicate_or_missing_destination_inputs(): void
    {
        $file = File::factory()->folder()->create(['user_id' => $this->user->id]);
        $this->postJson('/api/cloud/files/move', ['file_ids' => [], 'target_folder_id' => null])->assertUnprocessable();
        $this->postJson('/api/cloud/files/move', ['file_ids' => [$file->id, $file->id], 'target_folder_id' => null])->assertUnprocessable();
        $this->postJson('/api/cloud/files/move', ['file_ids' => [$file->id]])->assertUnprocessable()->assertJsonValidationErrors('target_folder_id');
    }

    public function test_invalid_note_tags_do_not_create_or_modify_a_note(): void
    {
        $this->postJson('/api/notes', ['title' => '无效标签', 'tags' => 'not-an-array'])
            ->assertUnprocessable()->assertJsonValidationErrors('tags');
        $this->assertDatabaseMissing('notes', ['title' => '无效标签']);

        $note = Note::factory()->create(['user_id' => $this->user->id, 'title' => '原始标题']);
        $this->putJson('/api/notes/' . $note->id, ['title' => '不应写入', 'tags' => [['nested']]])
            ->assertUnprocessable()->assertJsonValidationErrors('tags.0');
        $this->assertSame('原始标题', $note->fresh()->title);
    }

    public function test_note_tags_are_validated_and_can_be_cleared(): void
    {
        $response = $this->postJson('/api/notes', ['title' => '标签笔记', 'tags' => ['工作', '学习']])->assertCreated();
        $note = Note::findOrFail($response->json('data.id'));
        $this->assertCount(2, $note->tags);
        $this->assertTrue($note->tags->every(fn ($tag) => $tag->user_id === $this->user->id));
        $this->patchJson('/api/notes/' . $note->id, ['tags' => []])->assertOk();
        $this->assertCount(0, $note->fresh()->tags);
    }

    public function test_note_tag_limits_reject_oversized_values(): void
    {
        $this->postJson('/api/notes', ['title' => '笔记', 'tags' => array_fill(0, 51, '标签')])->assertUnprocessable();
        $this->postJson('/api/notes', ['title' => '笔记', 'tags' => [str_repeat('x', 256)]])->assertUnprocessable();
    }

    public function test_note_list_preview_is_portable_and_keeps_full_detail(): void
    {
        $content = str_repeat('中文内容', 100);
        $note = Note::factory()->create(['user_id' => $this->user->id, 'content_markdown' => $content]);
        $this->getJson('/api/notes')->assertOk()->assertJsonPath('data.0.content_markdown', mb_substr($content, 0, 240));
        $this->getJson('/api/notes/' . $note->id)->assertOk()->assertJsonPath('data.content_markdown', $content);
    }

    public function test_file_type_filters_and_statistics_handle_uppercase_and_apple_documents(): void
    {
        File::factory()->create(['user_id' => $this->user->id, 'is_folder' => false, 'extension' => 'PNG', 'size' => 7]);
        File::factory()->create(['user_id' => $this->user->id, 'is_folder' => false, 'extension' => 'PAGES', 'size' => 10]);
        $this->getJson('/api/cloud/files?type=image')->assertOk()->assertJsonCount(1);
        $this->getJson('/api/cloud/files?type=document')->assertOk()->assertJsonCount(1);
        $this->getJson('/api/cloud/statistics')->assertOk()
            ->assertJsonFragment(['file_type' => '图片', 'count' => 1, 'total_size' => 7])
            ->assertJsonFragment(['file_type' => '文档', 'count' => 1, 'total_size' => 10]);
    }
}
