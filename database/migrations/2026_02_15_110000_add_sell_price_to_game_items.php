<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_items', function (Blueprint $table) {
            $table->unsignedInteger('sell_price')->nullable()->after('slot_index');
        });
    }

    public function down(): void
    {
        Schema::table('game_items', function (Blueprint $table) {
            $table->dropColumn('sell_price');
        });
    }
};
