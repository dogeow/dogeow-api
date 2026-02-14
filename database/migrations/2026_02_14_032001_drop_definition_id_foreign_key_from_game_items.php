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
        Schema::table('game_items', function (Blueprint $table) {
            $table->dropForeign(['definition_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_items', function (Blueprint $table) {
            $table->foreign('definition_id')->references('id')->on('game_item_definitions');
        });
    }
};
