<?php

namespace Tests\Feature\Controllers\Word;

use App\Models\User;
use App\Models\Word\Book;
use App\Models\Word\Category;
use App\Models\Word\Word;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BookControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createCategory(array $attributes = []): Category
    {
        return Category::create(array_merge([
            'name' => 'Test Category',
            'description' => 'Test Category Description',
            'sort_order' => 1,
        ], $attributes));
    }

    private function createBook(array $attributes = []): Book
    {
        $category = $this->createCategory();

        return Book::create(array_merge([
            'word_category_id' => $category->id,
            'name' => 'Test Book',
            'description' => 'Test Book Description',
            'difficulty' => 1,
            'total_words' => 0,
            'sort_order' => 1,
        ], $attributes));
    }

    private function createWord(array $attributes = []): Word
    {
        return Word::create(array_merge([
            'content' => 'test',
            'phonetic_us' => '/test/',
            'explanation' => json_encode(['en' => 'test', 'zh' => '测试']),
            'example_sentences' => [],
            'difficulty' => 1,
            'frequency' => 1,
        ], $attributes));
    }

    public function test_can_get_book_list(): void
    {
        $user = User::factory()->create();
        $this->createBook();

        $response = $this->actingAs($user)
            ->getJson('/api/word/books');

        $response->assertStatus(200);
    }

    public function test_can_get_book_detail(): void
    {
        $user = User::factory()->create();
        $book = $this->createBook();

        $response = $this->actingAs($user)
            ->getJson('/api/word/books/' . $book->id);

        $response->assertStatus(200);
    }

    public function test_returns_404_for_nonexistent_book(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/api/word/books/99999');

        $response->assertStatus(404);
    }

    public function test_can_get_book_words(): void
    {
        $user = User::factory()->create();
        $book = $this->createBook();
        $word = $this->createWord();
        $book->words()->attach($word->id, ['sort_order' => 1]);

        $response = $this->actingAs($user)
            ->getJson('/api/word/books/' . $book->id . '/words');

        $response->assertStatus(200);
    }

    public function test_can_filter_book_words_by_mastered(): void
    {
        $user = User::factory()->create();
        $book = $this->createBook();
        $word = $this->createWord();
        $book->words()->attach($word->id, ['sort_order' => 1]);

        $response = $this->actingAs($user)
            ->getJson('/api/word/books/' . $book->id . '/words?filter=mastered');

        $response->assertStatus(200);
    }

    public function test_can_filter_book_words_by_difficult(): void
    {
        $user = User::factory()->create();
        $book = $this->createBook();
        $word = $this->createWord();
        $book->words()->attach($word->id, ['sort_order' => 1]);

        $response = $this->actingAs($user)
            ->getJson('/api/word/books/' . $book->id . '/words?filter=difficult');

        $response->assertStatus(200);
    }

    public function test_can_filter_book_words_by_simple(): void
    {
        $user = User::factory()->create();
        $book = $this->createBook();
        $word = $this->createWord();
        $book->words()->attach($word->id, ['sort_order' => 1]);

        $response = $this->actingAs($user)
            ->getJson('/api/word/books/' . $book->id . '/words?filter=simple');

        $response->assertStatus(200);
    }

    public function test_can_search_book_words_by_keyword(): void
    {
        $user = User::factory()->create();
        $book = $this->createBook();
        $word = $this->createWord(['content' => 'hello']);
        $book->words()->attach($word->id, ['sort_order' => 1]);

        $response = $this->actingAs($user)
            ->getJson('/api/word/books/' . $book->id . '/words?keyword=hello');

        $response->assertStatus(200);
    }

    public function test_requires_authentication_for_book_list(): void
    {
        $response = $this->getJson('/api/word/books');

        $response->assertStatus(401);
    }

    public function test_requires_authentication_for_book_detail(): void
    {
        $book = $this->createBook();

        $response = $this->getJson('/api/word/books/' . $book->id);

        $response->assertStatus(401);
    }

    public function test_book_counts_reflect_existing_words_instead_of_stale_metadata(): void
    {
        $user = User::factory()->create();
        $book = $this->createBook(['name' => '英语四级词汇', 'total_words' => 1316]);
        for ($index = 1; $index <= 5; $index++) {
            $word = $this->createWord(['content' => 'actual-' . $index]);
            $book->words()->attach($word->id);
        }
        // 无外键的历史库可能残留悬空关联，不能把不存在的单词计入数量。
        DB::table('word_book_word')->insert([
            'word_book_id' => $book->id, 'word_id' => 999999, 'sort_order' => 10,
        ]);

        $this->actingAs($user)->getJson('/api/word/books')
            ->assertOk()->assertJsonPath('data.0.total_words', 5);
        $this->getJson('/api/word/books/' . $book->id)
            ->assertOk()->assertJsonPath('data.total_words', 5);
        $this->getJson('/api/word/books/' . $book->id . '/words')
            ->assertOk()->assertJsonPath('meta.total', 5);
        $this->putJson('/api/word/settings', ['current_book_id' => $book->id])
            ->assertOk()->assertJsonPath('setting.current_book.total_words', 5);
        $this->getJson('/api/word/settings')
            ->assertOk()->assertJsonPath('current_book.total_words', 5);
        $this->assertDatabaseHas('word_books', ['id' => $book->id, 'total_words' => 1316]);
    }
}
