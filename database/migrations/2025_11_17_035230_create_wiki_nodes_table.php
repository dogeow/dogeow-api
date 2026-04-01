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
        Schema::create('wiki_nodes', function (Blueprint $table) {
            $table->id()->comment('Wiki 节点 ID');
            $table->string('title')->comment('节点标题');
            $table->string('slug')->unique()->comment('URL 别名（唯一）');
            $table->json('tags')->nullable()->comment('标签列表（JSON）');
            $table->text('summary')->nullable()->comment('节点摘要');
            $table->text('content')->nullable()->comment('内容（Slate JSON）');
            $table->text('content_markdown')->nullable()->comment('内容（Markdown）');
            $table->timestamps();
        });
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE wiki_nodes COMMENT = 'Wiki 节点表'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wiki_nodes');
    }
};
