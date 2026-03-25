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
        Schema::table('game_map_definitions', function (Blueprint $table) {
            $table->text('icon_prompt')->nullable()->after('background')->comment('AI生成地图背景提示词');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_map_definitions', function (Blueprint $table) {
            $table->dropColumn('icon_prompt');
        });
    }
};
