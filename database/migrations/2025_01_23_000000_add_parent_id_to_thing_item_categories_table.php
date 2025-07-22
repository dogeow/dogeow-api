<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 为物品分类表添加parent_id字段以支持两级分类
 */
return new class extends Migration
{
    /**
     * 运行迁移
     * 添加parent_id字段支持两级分类结构
     */
    public function up(): void
    {
        Schema::table('thing_item_categories', function (Blueprint $table) {
            $table->unsignedBigInteger('parent_id')->nullable()->after('name');
            $table->foreign('parent_id')->references('id')->on('thing_item_categories')->onDelete('cascade');
        });
    }

    /**
     * 回滚迁移
     * 删除parent_id字段
     */
    public function down(): void
    {
        Schema::table('thing_item_categories', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropColumn('parent_id');
        });
    }
};