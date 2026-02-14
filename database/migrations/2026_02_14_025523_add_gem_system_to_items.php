<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('game_item_definitions', function (Blueprint $table) {
            // 添加宝石插槽（装备上的宝石槽位）
            $table->unsignedInteger('sockets')->default(0)->after('sub_type');

            // 添加宝石属性（如果是宝石物品，存储其属性）
            $table->json('gem_stats')->nullable()->after('sockets');
        });

        // 修改 enum 类型添加 gem 选项需要使用 DBAL
        DB::statement("ALTER TABLE game_item_definitions MODIFY COLUMN type ENUM('weapon', 'helmet', 'armor', 'gloves', 'boots', 'belt', 'ring', 'ring1', 'ring2', 'amulet', 'potion', 'gem') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_item_definitions', function (Blueprint $table) {
            $table->dropColumn('sockets');
            $table->dropColumn('gem_stats');
        });
    }
};
