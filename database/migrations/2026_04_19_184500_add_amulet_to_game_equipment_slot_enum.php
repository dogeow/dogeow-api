<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('game_equipment') || DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(<<<'SQL'
            ALTER TABLE game_equipment
            MODIFY COLUMN slot ENUM('weapon','helmet','armor','gloves','boots','belt','ring','amulet')
            NOT NULL
            COMMENT '装备槽位：weapon 武器/helmet 头盔/armor 盔甲/gloves 手套/boots 靴子/belt 腰带/ring 戒指/amulet 护符'
        SQL);
    }

    public function down(): void
    {
        if (! Schema::hasTable('game_equipment') || DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::table('game_equipment')
            ->where('slot', 'amulet')
            ->update(['slot' => 'ring']);

        DB::statement(<<<'SQL'
            ALTER TABLE game_equipment
            MODIFY COLUMN slot ENUM('weapon','helmet','armor','gloves','boots','belt','ring')
            NOT NULL
            COMMENT '装备槽位：weapon 武器/helmet 头盔/armor 盔甲/gloves 手套/boots 靴子/belt 腰带/ring 戒指'
        SQL);
    }
};
