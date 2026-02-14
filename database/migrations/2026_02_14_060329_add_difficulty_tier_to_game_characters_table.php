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
            $table->unsignedTinyInteger('difficulty_tier')->default(0)->after('last_combat_at')
                ->comment('0=普通 1=困难 2=高手 3=大师 4-9=痛苦1-6');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_characters', function (Blueprint $table) {
            $table->dropColumn('difficulty_tier');
        });
    }
};
