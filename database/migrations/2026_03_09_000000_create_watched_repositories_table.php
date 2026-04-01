<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('watched_repositories', function (Blueprint $table) {
            $table->id()->comment('记录 ID');
            $table->unsignedBigInteger('user_id')->comment('所属用户 ID');
            $table->string('provider')->default('github')->comment('代码托管平台（github/gitlab）');
            $table->string('owner')->comment('仓库所有者');
            $table->string('repo')->comment('仓库名');
            $table->string('full_name')->comment('仓库全名（owner/repo）');
            $table->string('html_url', 500)->comment('仓库页面 URL');
            $table->string('default_branch')->nullable()->comment('默认分支');
            $table->string('language')->nullable()->comment('主要编程语言');
            $table->string('ecosystem')->nullable()->comment('包管理生态（npm/composer）');
            $table->string('package_name')->nullable()->comment('包名（如 package.json 的 name）');
            $table->string('manifest_path')->nullable()->comment('包清单文件路径');
            $table->string('latest_version')->nullable()->comment('最新发布版本号');
            $table->string('latest_source_type')->nullable()->comment('最新版本来源类型（release/tag/commit）');
            $table->string('latest_release_url', 500)->nullable()->comment('最新发布页面 URL');
            $table->text('description')->nullable()->comment('仓库描述');
            $table->timestamp('latest_release_published_at')->nullable()->comment('最新发布时间');
            $table->timestamp('last_checked_at')->nullable()->comment('最后检查时间');
            $table->text('last_error')->nullable()->comment('最后一次检查错误信息');
            $table->json('metadata')->nullable()->comment('额外元数据（JSON）');
            $table->timestamps();

            $table->index('user_id');
            $table->unique(['user_id', 'provider', 'owner', 'repo'], 'watched_repositories_user_repo_unique');
            $table->index(['user_id', 'latest_release_published_at']);
        });
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE watched_repositories COMMENT = '监控仓库表'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('watched_repositories');
    }
};
