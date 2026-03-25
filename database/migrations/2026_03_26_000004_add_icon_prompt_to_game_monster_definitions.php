<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_monster_definitions', function (Blueprint $table) {
            $table->text('icon_prompt')->nullable()->after('icon')->comment('AI生成怪物图标提示词');
        });
    }

    public function down(): void
    {
        Schema::table('game_monster_definitions', function (Blueprint $table) {
            $table->dropColumn('icon_prompt');
        });
    }
};
