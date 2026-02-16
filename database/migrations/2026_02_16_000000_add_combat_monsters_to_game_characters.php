<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_characters', function (Blueprint $table) {
            $table->json('combat_monsters')->nullable()->after('combat_monster_max_hp');
        });
    }

    public function down(): void
    {
        Schema::table('game_characters', function (Blueprint $table) {
            $table->dropColumn('combat_monsters');
        });
    }
};
