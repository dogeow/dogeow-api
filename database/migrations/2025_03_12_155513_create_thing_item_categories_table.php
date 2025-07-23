<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 创建物品分类表迁移
 * 包含分类名称、父分类ID和所属用户，支持两级分类
 */
return new class extends Migration
{
    /**
     * 运行迁移
     * 创建item_categories表，包含以下字段：
     * - id: 主键
     * - name: 分类名称
     * - parent_id: 父分类ID（可为空，支持两级分类）
     * - user_id: 所属用户ID
     */
    public function up(): void
    {
        Schema::create('thing_item_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->timestamps();
            
            // 添加外键约束
            $table->foreign('parent_id')->references('id')->on('thing_item_categories')->onDelete('cascade');
        });
    }

    /**
     * 回滚迁移
     * 删除item_categories表
     */
    public function down(): void
    {
        Schema::dropIfExists('thing_item_categories');
    }
};
