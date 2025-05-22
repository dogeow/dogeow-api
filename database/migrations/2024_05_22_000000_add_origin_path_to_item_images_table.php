<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('item_images', function (Blueprint $table) {
            $table->string('origin_path')->nullable()->after('thumbnail_path');
        });
    }

    public function down()
    {
        Schema::table('item_images', function (Blueprint $table) {
            $table->dropColumn('origin_path');
        });
    }
}; 