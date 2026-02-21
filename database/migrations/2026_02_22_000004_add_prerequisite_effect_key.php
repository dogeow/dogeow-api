<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_skill_definitions', function (Blueprint $table) {
            $table->string('prerequisite_effect_key', 64)->nullable()->after('prerequisite_skill_id')->comment('前置技能效果键');
        });
    }

    public function down(): void
    {
        Schema::table('game_skill_definitions', function (Blueprint $table) {
            $table->dropColumn('prerequisite_effect_key');
        });
    }
};
