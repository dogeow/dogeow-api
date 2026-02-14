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
        // 删除技能系统废弃的字段
        Schema::table('game_character_skills', function (Blueprint $table) {
            $table->dropColumn('level');
            $table->dropColumn('slot_index');
        });

        Schema::table('game_skill_definitions', function (Blueprint $table) {
            $table->dropColumn('max_level');
            $table->dropColumn('base_damage');
            $table->dropColumn('damage_per_level');
            $table->dropColumn('mana_cost_per_level');
        });

        // 删除地图系统废弃的字段
        Schema::table('game_character_maps', function (Blueprint $table) {
            $table->dropColumn('unlocked');
            $table->dropColumn('teleport_unlocked');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 恢复技能系统废弃的字段
        Schema::table('game_character_skills', function (Blueprint $table) {
            $table->unsignedInteger('level')->default(1);
            $table->unsignedInteger('slot_index')->nullable();
        });

        Schema::table('game_skill_definitions', function (Blueprint $table) {
            $table->unsignedInteger('max_level')->default(5);
            $table->unsignedInteger('base_damage')->default(10);
            $table->unsignedInteger('damage_per_level')->default(2);
            $table->unsignedInteger('mana_cost_per_level')->default(1);
        });

        // 恢复地图系统废弃的字段
        Schema::table('game_character_maps', function (Blueprint $table) {
            $table->boolean('unlocked')->default(true);
            $table->boolean('teleport_unlocked')->default(false);
        });
    }
};
