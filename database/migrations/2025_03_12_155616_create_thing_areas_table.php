<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 创建区域表迁移
 * 包含区域名称和所属用户
 */
return new class extends Migration
{
    /**
     * 运行迁移
     * 创建areas表，包含以下字段：
     * - id: 主键
     * - name: 区域名称
     * - user_id: 所属用户ID
     */
    public function up(): void
    {
        Schema::create('thing_areas', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();
        });
    }

    /**
     * 回滚迁移
     * 删除areas表
     */
    public function down(): void
    {
        Schema::dropIfExists('thing_areas');
    }
};
