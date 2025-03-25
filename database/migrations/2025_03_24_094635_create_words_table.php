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
        Schema::create('words', function (Blueprint $table) {
            $table->id();
            $table->foreignId('word_book_id')->comment('所属单词书ID');
            $table->string('content', 100)->comment('单词内容');
            $table->string('phonetic_uk', 100)->nullable()->comment('英式音标');
            $table->string('phonetic_us', 100)->nullable()->comment('美式音标');
            $table->string('audio_uk')->nullable()->comment('英式发音音频');
            $table->string('audio_us')->nullable()->comment('美式发音音频');
            $table->text('explanation')->comment('单词释义');
            $table->text('example_sentences')->nullable()->comment('例句(JSON格式)');
            $table->text('synonyms')->nullable()->comment('同义词');
            $table->text('antonyms')->nullable()->comment('反义词');
            $table->text('notes')->nullable()->comment('笔记');
            $table->integer('difficulty')->default(1)->comment('难度(1-5)');
            $table->integer('frequency')->default(1)->comment('常见度(1-5)');
            $table->timestamps();
            $table->softDeletes();
            
            // 索引
            $table->index('content');
            $table->index('word_book_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('words');
    }
};
