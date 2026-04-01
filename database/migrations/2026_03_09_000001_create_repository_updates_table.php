<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repository_updates', function (Blueprint $table) {
            $table->id()->comment('更新记录 ID');
            $table->unsignedBigInteger('watched_repository_id')->comment('所属监控仓库 ID');
            $table->string('source_type')->comment('更新来源类型（release/tag/commit）');
            $table->string('source_id')->nullable()->comment('来源平台的原始 ID');
            $table->string('version')->nullable()->comment('版本号');
            $table->string('title')->nullable()->comment('更新标题');
            $table->string('release_url', 500)->nullable()->comment('发布页面 URL');
            $table->longText('body')->nullable()->comment('发布说明原文');
            $table->text('ai_summary')->nullable()->comment('AI 生成的更新摘要');
            $table->timestamp('published_at')->nullable()->comment('发布时间');
            $table->json('metadata')->nullable()->comment('额外元数据（JSON）');
            $table->timestamps();

            $table->index('watched_repository_id');
            $table->unique(
                ['watched_repository_id', 'source_type', 'source_id'],
                'repository_updates_repo_source_unique'
            );
            $table->index(['watched_repository_id', 'published_at']);
        });
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE repository_updates COMMENT = '仓库更新记录表'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('repository_updates');
    }
};
