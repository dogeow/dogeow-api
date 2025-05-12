<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('thing_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('color')->default('#3b82f6'); // 默认蓝色
            $table->timestamps();
            $table->softDeletes();
        });
        
        // 创建物品与标签的多对多关联表
        Schema::create('thing_item_tag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->references('id')->on('thing_items')->onDelete('cascade');
            $table->foreignId('thing_tag_id')->references('id')->on('thing_tags')->onDelete('cascade');
            $table->timestamps();
            
            // 确保一个物品不会重复添加同一个标签
            $table->unique(['item_id', 'thing_tag_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('thing_item_tag');
        Schema::dropIfExists('thing_tags');
    }
}; 