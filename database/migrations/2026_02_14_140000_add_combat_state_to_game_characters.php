<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_characters', function (Blueprint $table) {
            $table->unsignedBigInteger('combat_monster_id')->nullable()->after('last_combat_at');
            $table->unsignedTinyInteger('combat_monster_level')->nullable()->after('combat_monster_id');
            $table->unsignedInteger('combat_monster_hp')->nullable()->after('combat_monster_level');
            $table->unsignedInteger('combat_monster_max_hp')->nullable()->after('combat_monster_hp');
            $table->unsignedInteger('combat_total_damage_dealt')->default(0)->after('combat_monster_max_hp');
            $table->unsignedInteger('combat_total_damage_taken')->default(0)->after('combat_total_damage_dealt');
            $table->unsignedInteger('combat_rounds')->default(0)->after('combat_total_damage_taken');
            $table->json('combat_skills_used')->nullable()->after('combat_rounds');
            $table->json('combat_skill_cooldowns')->nullable()->after('combat_skills_used');
            $table->timestamp('combat_started_at')->nullable()->after('combat_skill_cooldowns');
        });
    }

    public function down(): void
    {
        Schema::table('game_characters', function (Blueprint $table) {
            $table->dropColumn([
                'combat_monster_id',
                'combat_monster_level',
                'combat_monster_hp',
                'combat_monster_max_hp',
                'combat_total_damage_dealt',
                'combat_total_damage_taken',
                'combat_rounds',
                'combat_skills_used',
                'combat_skill_cooldowns',
                'combat_started_at',
            ]);
        });
    }
};
