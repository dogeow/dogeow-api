<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('word_book_word', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('word_book_id')->comment('单词书 ID');
            $table->unsignedBigInteger('word_id')->comment('单词 ID');
            $table->integer('sort_order')->default(0)->comment('排序');
            $table->timestamps();

            $table->unique(['word_book_id', 'word_id']);
            $table->index('word_book_id');
            $table->index('word_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('word_book_word');
    }
};
