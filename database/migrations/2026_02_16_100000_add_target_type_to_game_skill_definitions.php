<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 技能目标类型：single=单体，all=群体(AOE)
     */
    public function up(): void
    {
        Schema::table('game_skill_definitions', function (Blueprint $table) {
            $table->string('target_type', 16)->default('single')->after('effects');
        });
    }

    public function down(): void
    {
        Schema::table('game_skill_definitions', function (Blueprint $table) {
            $table->dropColumn('target_type');
        });
    }
};
