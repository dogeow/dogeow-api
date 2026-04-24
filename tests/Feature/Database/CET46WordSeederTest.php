<?php

namespace Tests\Feature\Database;

use App\Models\Word\Book;
use App\Models\Word\Category;
use App\Models\Word\Word;
use Database\Seeders\Word\CET46WordSeeder;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CET46WordSeederTest extends TestCase
{
    public function test_word_seeder_uses_schema_foreign_key_wrapper(): void
    {
        Category::query()->count();

        Schema::partialMock()
            ->shouldReceive('withoutForeignKeyConstraints')
            ->once()
            ->andReturnUsing(static fn (callable $callback): mixed => $callback());

        $this->seed(CET46WordSeeder::class);
    }

    public function test_word_seeder_runs_on_non_mysql_drivers_and_populates_books(): void
    {
        $this->seed(CET46WordSeeder::class);

        $this->assertSame(5, Category::query()->count());
        $this->assertSame(5, Book::query()->count());
        $this->assertGreaterThan(0, Word::query()->count());

        $cet4Book = Book::query()->where('name', '英语四级词汇')->firstOrFail();

        $this->assertGreaterThan(0, $cet4Book->total_words);
        $this->assertSame($cet4Book->total_words, $cet4Book->words()->count());
    }
}
