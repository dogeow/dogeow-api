<?php

namespace Tests\Unit\Models\Note;

use Tests\TestCase;
use App\Models\Note\Note;
use App\Models\Note\NoteCategory;
use App\Models\Note\NoteTag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class NoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_note_can_be_created()
    {
        $user = User::factory()->create();
        $category = NoteCategory::factory()->create();
        
        $note = Note::factory()->create([
            'user_id' => $user->id,
            'note_category_id' => $category->id,
            'title' => 'Test Note',
            'content' => 'Test content',
            'content_markdown' => '# Test Note',
            'is_draft' => false,
        ]);

        $this->assertDatabaseHas('notes', [
            'id' => $note->id,
            'title' => 'Test Note',
            'user_id' => $user->id,
            'note_category_id' => $category->id,
        ]);
    }

    public function test_note_belongs_to_user()
    {
        $user = User::factory()->create();
        $note = Note::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $note->user);
        $this->assertEquals($user->id, $note->user->id);
    }

    public function test_note_belongs_to_category()
    {
        $category = NoteCategory::factory()->create();
        $note = Note::factory()->create(['note_category_id' => $category->id]);

        $this->assertInstanceOf(NoteCategory::class, $note->category);
        $this->assertEquals($category->id, $note->category->id);
    }

    public function test_note_can_have_tags()
    {
        $note = Note::factory()->create();
        $tag1 = NoteTag::factory()->create();
        $tag2 = NoteTag::factory()->create();

        $note->tags()->attach([$tag1->id, $tag2->id]);

        $this->assertCount(2, $note->tags);
        $this->assertTrue($note->tags->contains($tag1));
        $this->assertTrue($note->tags->contains($tag2));
    }

    public function test_note_can_be_soft_deleted()
    {
        $note = Note::factory()->create();

        $note->delete();

        $this->assertSoftDeleted('notes', ['id' => $note->id]);
    }

    public function test_note_fillable_attributes()
    {
        $data = [
            'user_id' => User::factory()->create()->id,
            'note_category_id' => NoteCategory::factory()->create()->id,
            'title' => 'Test Note',
            'content' => 'Test content',
            'content_markdown' => '# Test Note',
            'is_draft' => true,
        ];

        $note = Note::create($data);

        $this->assertEquals($data['title'], $note->title);
        $this->assertEquals($data['content'], $note->content);
        $this->assertEquals($data['content_markdown'], $note->content_markdown);
        $this->assertEquals($data['is_draft'], $note->is_draft);
    }

    public function test_note_has_dates_casted()
    {
        $note = Note::factory()->create();

        $this->assertInstanceOf(\Carbon\Carbon::class, $note->created_at);
        $this->assertInstanceOf(\Carbon\Carbon::class, $note->updated_at);
    }

    public function test_note_can_be_draft()
    {
        $note = Note::factory()->create(['is_draft' => true]);

        $this->assertTrue($note->is_draft);
    }

    public function test_note_can_be_published()
    {
        $note = Note::factory()->create(['is_draft' => false]);

        $this->assertFalse($note->is_draft);
    }

    public function test_note_can_have_markdown_content()
    {
        $markdown = "# Title\n\nThis is **bold** text.";
        $note = Note::factory()->create(['content_markdown' => $markdown]);

        $this->assertEquals($markdown, $note->content_markdown);
    }

    public function test_note_can_have_plain_content()
    {
        $content = "This is plain text content.";
        $note = Note::factory()->create(['content' => $content]);

        $this->assertEquals($content, $note->content);
    }
} 