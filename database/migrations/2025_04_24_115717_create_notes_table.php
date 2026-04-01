<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('notes', function (Blueprint $table) {
            $table->id()->comment('笔记 ID');
            $table->unsignedBigInteger('user_id')->nullable()->index()->comment('所属用户 ID');
            $table->unsignedBigInteger('note_category_id')->nullable()->index()->comment('所属分类 ID');
            $table->string('title')->comment('笔记标题');
            $table->string('slug')->nullable()->unique()->comment('URL友好别名');
            $table->text('summary')->nullable()->comment('摘要');
            $table->boolean('is_wiki')->default(false)->comment('是否为 Wiki 文档');
            $table->text('content')->nullable()->comment('内容（Slate JSON）');
            $table->text('content_markdown')->nullable()->comment('内容（Markdown）');
            $table->boolean('is_draft')->default(false)->comment('是否为草稿');
            $table->timestamps();
            $table->softDeletes();
        });
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE notes COMMENT = '笔记表'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notes');
    }
};
