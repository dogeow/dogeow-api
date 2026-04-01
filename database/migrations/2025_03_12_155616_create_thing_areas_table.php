<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 创建区域表迁移
 * 包含区域名称和所属用户
 */
return new class extends Migration
{
    /**
     * 运行迁移
     * 创建 areas 表，包含以下字段：
     * - id: 主键
     * - name: 区域名称
     * - user_id: 所属用户 ID
     * - is_default: 是否为默认区域
     */
    public function up(): void
    {
        Schema::create('thing_areas', function (Blueprint $table) {
            $table->id()->comment('区域 ID');
            $table->string('name')->comment('区域名称');
            $table->unsignedBigInteger('user_id')->comment('所属用户 ID');
            $table->boolean('is_default')->default(false)->comment('是否为默认区域');
            $table->timestamps();
        });
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE thing_areas COMMENT = '物品管理：地理区域表'");
        }
    }

    /**
     * 回滚迁移
     * 删除 areas 表
     */
    public function down(): void
    {
        Schema::dropIfExists('thing_areas');
    }
};
