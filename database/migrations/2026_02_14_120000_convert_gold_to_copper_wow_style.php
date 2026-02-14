<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 金币改为三币制：金/银/铜，1金=100银，1银=100铜，存储为铜币（最小单位）
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_characters', function (Blueprint $table) {
            $table->unsignedBigInteger('copper')->default(0)->after('experience');
        });
        \DB::table('game_characters')->update([
            'copper' => \DB::raw('gold * 10000'),
        ]);
        Schema::table('game_characters', function (Blueprint $table) {
            $table->dropColumn('gold');
        });

        Schema::table('game_combat_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('copper_gained')->default(0)->after('experience_gained');
        });
        \DB::table('game_combat_logs')->update([
            'copper_gained' => \DB::raw('gold_gained * 10000'),
        ]);
        Schema::table('game_combat_logs', function (Blueprint $table) {
            $table->dropColumn('gold_gained');
        });
    }

    public function down(): void
    {
        Schema::table('game_characters', function (Blueprint $table) {
            $table->unsignedBigInteger('gold')->default(0)->after('experience');
        });
        \DB::table('game_characters')->update([
            'gold' => \DB::raw('copper / 10000'),
        ]);
        Schema::table('game_characters', function (Blueprint $table) {
            $table->dropColumn('copper');
        });

        Schema::table('game_combat_logs', function (Blueprint $table) {
            $table->unsignedInteger('gold_gained')->default(0)->after('experience_gained');
        });
        \DB::table('game_combat_logs')->update([
            'gold_gained' => \DB::raw('copper_gained / 10000'),
        ]);
        Schema::table('game_combat_logs', function (Blueprint $table) {
            $table->dropColumn('copper_gained');
        });
    }
};
