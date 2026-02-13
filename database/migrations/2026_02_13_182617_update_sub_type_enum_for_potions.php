<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 修改 sub_type 枚举，添加 hp 和 mp
        DB::statement("ALTER TABLE game_item_definitions MODIFY COLUMN sub_type ENUM('sword','axe','mace','staff','bow','dagger','cloth','leather','mail','plate','hp','mp') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE game_item_definitions MODIFY COLUMN sub_type ENUM('sword','axe','mace','staff','bow','dagger','cloth','leather','mail','plate') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL");
    }
};
