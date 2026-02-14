<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 允许清空 game_monster_definitions：将 game_combat_logs.monster_id 改为可空并 ON DELETE SET NULL，
     * 这样删除或清空怪物定义时战斗日志保留、monster_id 置为 null；或可先清空 combat_logs 再 truncate 定义表。
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            try {
                Schema::table('game_combat_logs', function (Blueprint $table) {
                    $table->dropForeign(['monster_id']);
                });
            } catch (\Throwable) {
                // 外键可能已被移除（项目不在数据库层使用外键）
            }
        }

        DB::statement('ALTER TABLE game_combat_logs MODIFY monster_id BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE game_combat_logs MODIFY monster_id BIGINT UNSIGNED NOT NULL');
    }
};
