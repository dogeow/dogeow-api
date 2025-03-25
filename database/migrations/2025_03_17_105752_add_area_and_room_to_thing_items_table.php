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
        Schema::table('thing_items', function (Blueprint $table) {
            $table->unsignedBigInteger('area_id')->nullable()->after('category_id');
            $table->unsignedBigInteger('room_id')->nullable()->after('area_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('thing_items', function (Blueprint $table) {
            $table->dropColumn('area_id');
            $table->dropColumn('room_id');
        });
    }
};
