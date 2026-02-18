<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 修改 enum 值：ring1, ring2, amulet -> ring
        DB::statement("ALTER TABLE game_equipment MODIFY COLUMN slot ENUM('weapon', 'helmet', 'armor', 'gloves', 'boots', 'belt', 'ring') NOT NULL COMMENT '装备槽位'");
    }

    public function down(): void
    {
        // 恢复原来的 enum 值
        DB::statement("ALTER TABLE game_equipment MODIFY COLUMN slot ENUM('weapon', 'helmet', 'armor', 'gloves', 'boots', 'belt', 'ring1', 'ring2', 'amulet') NOT NULL COMMENT '装备槽位'");
    }
};
