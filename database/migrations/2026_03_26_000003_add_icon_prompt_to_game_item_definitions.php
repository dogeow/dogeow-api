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
        Schema::table('game_item_definitions', function (Blueprint $table) {
            $table->text('icon_prompt')->nullable()->after('icon')->comment('AI生成物品图标提示词');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_item_definitions', function (Blueprint $table) {
            $table->dropColumn('icon_prompt');
        });
    }
};
