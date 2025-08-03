<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Note\Note;
use App\Models\Note\NoteCategory;
use App\Models\Note\NoteTag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NoteControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
    }

    public function test_index_returns_user_notes(): void
    {
        // Create notes for the authenticated user
        $userNotes = Note::factory()->count(3)->create([
            'user_id' => $this->user->id
        ]);

        // Create notes for another user (should not be returned)
        $otherUser = User::factory()->create();
        Note::factory()->count(2)->create([
            'user_id' => $otherUser->id
        ]);

        $response = $this->getJson('/api/notes');

        $response->assertStatus(200)
            ->assertJsonCount(3)
            ->assertJsonStructure([
                '*' => [
                    'id',
                    'title',
                    'content',
                    'content_markdown',
                    'is_draft',
                    'user_id',
                    'created_at',
                    'updated_at',
                    'category',
                    'tags'
                ]
            ]);

        // Verify only user's notes are returned
        $responseData = $response->json();
        foreach ($responseData as $note) {
            $this->assertEquals($this->user->id, $note['user_id']);
        }
    }

    public function test_store_creates_new_note(): void
    {
        $noteData = [
            'title' => 'Test Note',
            'content' => 'This is test content',
            'content_markdown' => '# Test Note\n\nThis is test content',
            'is_draft' => false
        ];

        $response = $this->postJson('/api/notes', $noteData);

        $response->assertStatus(201)
            ->assertJson([
                'title' => 'Test Note',
                'content' => 'This is test content',
                'content_markdown' => '# Test Note\n\nThis is test content',
                'is_draft' => false,
                'user_id' => $this->user->id
            ]);

        $this->assertDatabaseHas('notes', [
            'title' => 'Test Note',
            'user_id' => $this->user->id
        ]);
    }

    public function test_store_creates_note_without_markdown(): void
    {
        $noteData = [
            'title' => 'Test Note',
            'content' => 'This is test content',
            'is_draft' => true
        ];

        $response = $this->postJson('/api/notes', $noteData);

        $response->assertStatus(201)
            ->assertJson([
                'title' => 'Test Note',
                'content' => 'This is test content',
                'content_markdown' => 'This is test content', // Should use content as markdown
                'is_draft' => true,
                'user_id' => $this->user->id
            ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->postJson('/api/notes', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['title']);
    }

    public function test_show_returns_note(): void
    {
        $note = Note::factory()->create([
            'user_id' => $this->user->id
        ]);

        $response = $this->getJson("/api/notes/{$note->id}");

        $response->assertStatus(200)
            ->assertJson([
                'id' => $note->id,
                'title' => $note->title,
                'user_id' => $this->user->id
            ]);
    }

    public function test_show_returns_404_for_other_user_note(): void
    {
        $otherUser = User::factory()->create();
        $note = Note::factory()->create([
            'user_id' => $otherUser->id
        ]);

        $response = $this->getJson("/api/notes/{$note->id}");

        $response->assertStatus(404);
    }

    public function test_show_returns_404_for_nonexistent_note(): void
    {
        $response = $this->getJson("/api/notes/99999");

        $response->assertStatus(404);
    }

    public function test_update_modifies_note(): void
    {
        $note = Note::factory()->create([
            'user_id' => $this->user->id
        ]);

        $updateData = [
            'title' => 'Updated Title',
            'content' => 'Updated content',
            'content_markdown' => '# Updated Title\n\nUpdated content',
            'is_draft' => true
        ];

        $response = $this->putJson("/api/notes/{$note->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'id' => $note->id,
                'title' => 'Updated Title',
                'content' => 'Updated content',
                'content_markdown' => '# Updated Title\n\nUpdated content',
                'is_draft' => true
            ]);

        $this->assertDatabaseHas('notes', [
            'id' => $note->id,
            'title' => 'Updated Title',
            'content' => 'Updated content'
        ]);
    }

    public function test_update_partial_fields(): void
    {
        $note = Note::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Original Title',
            'content' => 'Original content',
            'is_draft' => false
        ]);

        // Update only title
        $response = $this->putJson("/api/notes/{$note->id}", [
            'title' => 'New Title'
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'title' => 'New Title',
                'content' => 'Original content', // Should remain unchanged
                'is_draft' => false // Should remain unchanged
            ]);
    }

    public function test_update_content_without_markdown(): void
    {
        $note = Note::factory()->create([
            'user_id' => $this->user->id
        ]);

        $response = $this->putJson("/api/notes/{$note->id}", [
            'content' => 'New content without markdown'
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'content' => 'New content without markdown',
                'content_markdown' => 'New content without markdown' // Should use content as markdown
            ]);
    }

    public function test_update_validates_title(): void
    {
        $note = Note::factory()->create([
            'user_id' => $this->user->id
        ]);

        $response = $this->putJson("/api/notes/{$note->id}", [
            'title' => '' // Empty title should fail validation
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['title']);
    }

    public function test_update_returns_404_for_other_user_note(): void
    {
        $otherUser = User::factory()->create();
        $note = Note::factory()->create([
            'user_id' => $otherUser->id
        ]);

        $response = $this->putJson("/api/notes/{$note->id}", [
            'title' => 'Updated Title'
        ]);

        $response->assertStatus(404);
    }

    public function test_destroy_deletes_note(): void
    {
        $note = Note::factory()->create([
            'user_id' => $this->user->id
        ]);

        $response = $this->deleteJson("/api/notes/{$note->id}");

        $response->assertStatus(204);

        $this->assertDatabaseMissing('notes', [
            'id' => $note->id
        ]);
    }

    public function test_destroy_returns_404_for_other_user_note(): void
    {
        $otherUser = User::factory()->create();
        $note = Note::factory()->create([
            'user_id' => $otherUser->id
        ]);

        $response = $this->deleteJson("/api/notes/{$note->id}");

        $response->assertStatus(404);

        $this->assertDatabaseHas('notes', [
            'id' => $note->id
        ]);
    }

    public function test_destroy_returns_404_for_nonexistent_note(): void
    {
        $response = $this->deleteJson("/api/notes/99999");

        $response->assertStatus(404);
    }
} 