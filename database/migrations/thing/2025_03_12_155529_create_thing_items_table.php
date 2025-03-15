<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 创建物品表迁移
 * 包含物品的基本信息、数量、状态、过期时间、购买信息等
 */
return new class extends Migration
{
    /**
     * 运行迁移
     * 创建items表，包含以下字段：
     * - id: 主键
     * - name: 物品名称
     * - description: 物品描述
     * - user_id: 所属用户ID
     * - quantity: 数量，默认1
     * - status: 状态，默认active
     * - expiry_date: 过期时间
     * - purchase_date: 购买时间
     * - purchase_price: 购买价格
     * - category_id: 分类ID
     * - spot_id: 地点ID
     * - is_public: 是否公开
     */
    public function up(): void
    {
        Schema::create('thing_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('quantity')->default(1);
            $table->string('status')->default('active');
            $table->date('purchase_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->decimal('purchase_price', 10, 2)->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('spot_id')->nullable();
            $table->boolean('is_public')->default(false);
            $table->timestamps();
        });
    }

    /**
     * 回滚迁移
     * 删除items表
     */
    public function down(): void
    {
        Schema::dropIfExists('thing_items');
    }
};
