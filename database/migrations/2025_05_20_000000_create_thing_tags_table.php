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
        Schema::create('thing_tags', function (Blueprint $table) {
            $table->id()->comment('标签 ID');
            $table->unsignedBigInteger('user_id')->index()->comment('所属用户 ID');
            $table->string('name')->comment('标签名称');
            $table->string('color')->default('#3b82f6')->comment('标签颜色（默认蓝色）');
            $table->timestamps();
            $table->softDeletes();
        });
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE thing_tags COMMENT = '物品标签表'");
        }

        // 创建物品与标签的多对多关联表
        Schema::create('thing_item_tag', function (Blueprint $table) {
            $table->id()->comment('记录 ID');
            $table->unsignedBigInteger('item_id')->index()->comment('物品 ID');
            $table->unsignedBigInteger('thing_tag_id')->index()->comment('标签 ID');
            $table->timestamps();

            $table->unique(['item_id', 'thing_tag_id']);
        });
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE thing_item_tag COMMENT = '物品-标签关联表（多对多）'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('thing_item_tag');
        Schema::dropIfExists('thing_tags');
    }
};
