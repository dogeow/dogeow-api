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
            $table->boolean('auto_use_hp_potion')->default(false);
            $table->integer('hp_potion_threshold')->default(30);
            $table->boolean('auto_use_mp_potion')->default(false);
            $table->integer('mp_potion_threshold')->default(30);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_characters', function (Blueprint $table) {
            $table->dropColumn([
                'auto_use_hp_potion',
                'hp_potion_threshold',
                'auto_use_mp_potion',
                'mp_potion_threshold',
            ]);
        });
    }
};
