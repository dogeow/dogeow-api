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
        Schema::table('game_characters', function (Blueprint $table) {
            $table->unsignedInteger('current_hp')->nullable()->after('last_combat_at');
            $table->unsignedInteger('current_mana')->nullable()->after('current_hp');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_characters', function (Blueprint $table) {
            $table->dropColumn(['current_hp', 'current_mana']);
        });
    }
};
