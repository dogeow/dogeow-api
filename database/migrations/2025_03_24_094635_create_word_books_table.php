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
        Schema::create('word_books', function (Blueprint $table) {
            $table->id();
            $table->foreignId('word_category_id')->comment('所属分类ID');
            $table->string('name', 100)->comment('单词书名称');
            $table->string('cover_image')->nullable()->comment('封面图片');
            $table->text('description')->nullable()->comment('描述');
            $table->integer('total_words')->default(0)->comment('总单词数');
            $table->integer('difficulty')->default(1)->comment('难度(1-5)');
            $table->integer('sort_order')->default(0)->comment('排序');
            $table->boolean('is_active')->default(true)->comment('是否激活');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('word_books');
    }
};
