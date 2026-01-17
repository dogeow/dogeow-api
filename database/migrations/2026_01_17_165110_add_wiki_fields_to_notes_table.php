<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 先添加新字段
        Schema::table('notes', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('title');
            $table->text('summary')->nullable()->after('slug');
            $table->boolean('is_wiki')->default(false)->after('summary');
        });

        // 使用原生 SQL 修改 user_id 字段为可空
        // 注意：如果外键约束存在，可能需要先删除再重新添加
        try {
            DB::statement('ALTER TABLE notes MODIFY COLUMN user_id BIGINT UNSIGNED NULL');
        } catch (\Exception $e) {
            // 如果失败，尝试先删除外键约束
            $foreignKeys = DB::select("
                SELECT CONSTRAINT_NAME 
                FROM information_schema.KEY_COLUMN_USAGE 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'notes' 
                AND COLUMN_NAME = 'user_id' 
                AND REFERENCED_TABLE_NAME IS NOT NULL
            ");
            
            foreach ($foreignKeys as $fk) {
                DB::statement("ALTER TABLE notes DROP FOREIGN KEY {$fk->CONSTRAINT_NAME}");
            }
            
            DB::statement('ALTER TABLE notes MODIFY COLUMN user_id BIGINT UNSIGNED NULL');
            
            // 重新添加外键约束
            DB::statement('ALTER TABLE notes ADD CONSTRAINT notes_user_id_foreign FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->dropColumn(['slug', 'summary', 'is_wiki']);
        });
    }
};
