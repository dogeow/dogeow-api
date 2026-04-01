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
        Schema::create('cloud_files', function (Blueprint $table) {
            $table->id()->comment('文件 ID');
            $table->string('name')->comment('存储文件名');
            $table->string('original_name')->nullable()->comment('原始文件名');
            $table->string('path')->comment('存储路径');
            $table->string('mime_type')->nullable()->comment('MIME 类型');
            $table->string('extension', 20)->nullable()->comment('文件后缀');
            $table->unsignedBigInteger('size')->default(0)->comment('文件大小（字节）');
            $table->unsignedBigInteger('parent_id')->nullable()->comment('父文件夹 ID（null 为根目录）');
            $table->unsignedBigInteger('user_id')->comment('所属用户 ID');
            $table->boolean('is_folder')->default(false)->comment('是否为文件夹');
            $table->text('description')->nullable()->comment('文件描述');
            $table->timestamps();

            $table->index('parent_id');
            $table->index('user_id');
            $table->index('is_folder');
            $table->index(['user_id', 'parent_id'], 'cloud_files_user_parent_idx');
            $table->index(['user_id', 'is_folder'], 'cloud_files_user_folder_idx');
            $table->index(['user_id', 'parent_id', 'is_folder'], 'cloud_files_user_parent_folder_idx');
            $table->index('extension', 'cloud_files_extension_idx');
            $table->index('created_at', 'cloud_files_created_at_idx');
        });
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE cloud_files COMMENT = '云盘文件表（支持文件夹层级）'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cloud_files');
    }
};
