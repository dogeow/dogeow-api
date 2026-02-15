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
            $table->unsignedTinyInteger('max_level')->default(10)->after('cooldown');
            $table->unsignedSmallInteger('base_damage')->default(10)->after('max_level');
            $table->unsignedSmallInteger('damage_per_level')->default(5)->after('base_damage');
            $table->unsignedSmallInteger('mana_cost_per_level')->default(0)->after('damage_per_level');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_skill_definitions', function (Blueprint $table) {
            $table->dropColumn([
                'max_level',
                'base_damage',
                'damage_per_level',
                'mana_cost_per_level',
            ]);
        });
    }
};
