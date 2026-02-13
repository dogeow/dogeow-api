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
        // 修改地图定义表 - 支持100级和更高的传送费用
        Schema::table('game_map_definitions', function (Blueprint $table) {
            $table->unsignedMediumInteger('min_level')->default(1)->change();
            $table->unsignedMediumInteger('max_level')->default(100)->change();
            $table->unsignedMediumInteger('teleport_cost')->default(0)->change();
        });

        // 修改角色表 - 支持100级
        Schema::table('game_characters', function (Blueprint $table) {
            $table->unsignedMediumInteger('level')->default(1)->change();
            $table->unsignedMediumInteger('skill_points')->default(0)->change();
            $table->unsignedMediumInteger('stat_points')->default(0)->change();
        });

        // 修改怪物定义表 - 支持100级
        Schema::table('game_monster_definitions', function (Blueprint $table) {
            $table->unsignedMediumInteger('level')->default(1)->change();
        });

        // 修改物品定义表 - 支持100级需求
        Schema::table('game_item_definitions', function (Blueprint $table) {
            $table->unsignedMediumInteger('required_level')->default(1)->change();
        });

        // 修改技能定义表 - 支持更高级别
        Schema::table('game_character_skills', function (Blueprint $table) {
            $table->unsignedMediumInteger('level')->default(1)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_map_definitions', function (Blueprint $table) {
            $table->unsignedTinyInteger('min_level')->default(1)->change();
            $table->unsignedTinyInteger('max_level')->default(10)->change();
            $table->unsignedSmallInteger('teleport_cost')->default(0)->change();
        });

        Schema::table('game_characters', function (Blueprint $table) {
            $table->unsignedTinyInteger('level')->default(1)->change();
            $table->unsignedTinyInteger('skill_points')->default(0)->change();
            $table->unsignedTinyInteger('stat_points')->default(0)->change();
        });

        Schema::table('game_monster_definitions', function (Blueprint $table) {
            $table->unsignedTinyInteger('level')->default(1)->change();
        });

        Schema::table('game_item_definitions', function (Blueprint $table) {
            $table->unsignedTinyInteger('required_level')->default(1)->change();
        });

        Schema::table('game_character_skills', function (Blueprint $table) {
            $table->unsignedTinyInteger('level')->default(1)->change();
        });
    }
};
