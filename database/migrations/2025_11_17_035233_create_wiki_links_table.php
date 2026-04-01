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
        Schema::create('wiki_links', function (Blueprint $table) {
            $table->id()->comment('链接 ID');
            $table->unsignedBigInteger('source_id')->index()->comment('源 Wiki 节点 ID');
            $table->unsignedBigInteger('target_id')->index()->comment('目标 Wiki 节点 ID');
            $table->string('type')->nullable()->comment('链接类型');
            $table->timestamps();

            $table->unique(['source_id', 'target_id']);
        });
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE wiki_links COMMENT = 'Wiki 节点双向链接表'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wiki_links');
    }
};
