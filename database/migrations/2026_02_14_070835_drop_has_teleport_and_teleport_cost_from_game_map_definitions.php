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
        Schema::table('game_map_definitions', function (Blueprint $table) {
            $table->dropColumn(['has_teleport', 'teleport_cost']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_map_definitions', function (Blueprint $table) {
            $table->boolean('has_teleport')->default(false)->after('monster_ids');
            $table->unsignedMediumInteger('teleport_cost')->default(0)->after('has_teleport');
        });
    }
};
