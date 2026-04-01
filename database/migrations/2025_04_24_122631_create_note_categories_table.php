<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('note_categories', function (Blueprint $table) {
            $table->id()->comment('分类 ID');
            $table->unsignedBigInteger('user_id')->index()->comment('所属用户 ID');
            $table->string('name')->comment('分类名称');
            $table->string('description')->nullable()->comment('分类描述');
            $table->timestamps();
            $table->softDeletes();
        });
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE note_categories COMMENT = '笔记分类表'");
        }

        // 给笔记表添加分类 ID 字段
        Schema::table('notes', function (Blueprint $table) {
            $table->unsignedBigInteger('note_category_id')->nullable()->after('user_id')->index()->comment('所属分类 ID');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->dropColumn('note_category_id');
        });

        Schema::dropIfExists('note_categories');
    }
};
