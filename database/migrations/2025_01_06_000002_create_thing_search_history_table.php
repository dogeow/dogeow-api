<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 创建物品搜索历史表迁移
 * 用于记录用户的搜索历史，提供搜索建议和统计功能
 */
return new class extends Migration
{
    /**
     * 运行迁移
     * 创建search_history表，包含以下字段：
     * - id: 主键
     * - user_id: 用户ID
     * - search_term: 搜索关键词
     * - results_count: 结果数量
     * - filters: 使用的过滤器（JSON格式）
     * - created_at, updated_at: 时间戳
     */
    public function up(): void
    {
        Schema::create('thing_search_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->comment('用户ID（未登录用户为null）');
            $table->string('search_term')->comment('搜索关键词');
            $table->integer('results_count')->default(0)->comment('结果数量');
            $table->json('filters')->nullable()->comment('使用的过滤器');
            $table->string('ip_address', 45)->nullable()->comment('IP地址');
            $table->timestamps();

            // 外键约束
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            // 索引
            $table->index('user_id');
            $table->index('search_term');
            $table->index('created_at');
        });
    }

    /**
     * 回滚迁移
     * 删除search_history表
     */
    public function down(): void
    {
        Schema::dropIfExists('thing_search_history');
    }
};
