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
        Schema::table('game_skill_definitions', function (Blueprint $table) {
            $table->text('icon_prompt')->nullable()->after('effect_key')->comment('AI生成技能图标提示词');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_skill_definitions', function (Blueprint $table) {
            $table->dropColumn('icon_prompt');
        });
    }
};
