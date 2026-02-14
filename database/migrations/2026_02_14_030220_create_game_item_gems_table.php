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
        Schema::create('game_item_gems', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('item_id')->comment('装备物品ID');
            $table->unsignedBigInteger('gem_definition_id')->comment('宝石定义ID');
            $table->unsignedInteger('socket_index')->comment('插槽位置（0开始）');
            $table->timestamps();

            $table->index('item_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_item_gems');
    }
};
