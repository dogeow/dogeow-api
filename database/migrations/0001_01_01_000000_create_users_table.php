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
        Schema::create('users', function (Blueprint $table) {
            $table->id()->comment('用户 ID');
            $table->string('name')->comment('用户名');
            $table->string('email')->unique()->comment('邮箱（唯一）');
            $table->string('github_id')->nullable()->unique()->comment('GitHub 用户 ID');
            $table->string('github_avatar')->nullable()->comment('GitHub 头像 URL');
            $table->timestamp('email_verified_at')->nullable()->comment('邮箱验证时间');
            $table->boolean('is_admin')->default(false)->comment('是否为管理员');
            $table->string('password')->comment('密码哈希');
            $table->rememberToken()->comment('记住登录 Token');
            $table->timestamps();
        });
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users COMMENT = '用户表'");
        }

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary()->comment('用户邮箱（主键）');
            $table->string('token')->comment('重置密码 Token');
            $table->timestamp('created_at')->nullable()->comment('Token 创建时间');
        });
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE password_reset_tokens COMMENT = '密码重置 Token 表'");
        }

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary()->comment('Session ID');
            $table->unsignedBigInteger('user_id')->nullable()->index()->comment('所属用户 ID（访客为 null）');
            $table->string('ip_address', 45)->nullable()->comment('客户端 IP 地址');
            $table->text('user_agent')->nullable()->comment('客户端 UA 字符串');
            $table->longText('payload')->comment('Session 序列化数据');
            $table->integer('last_activity')->index()->comment('最后活跃时间戳');
        });
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE sessions COMMENT = '用户会话表'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
