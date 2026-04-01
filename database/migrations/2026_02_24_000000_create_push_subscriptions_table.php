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
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id()->comment('订阅 ID');
            $table->string('subscribable_type')->comment('订阅者模型类名（多态）');
            $table->unsignedBigInteger('subscribable_id')->comment('订阅者模型 ID（多态）');
            $table->string('endpoint', 500)->comment('Web Push 推送端点 URL');
            $table->string('public_key')->nullable()->comment('VAPID 公鑰');
            $table->string('auth_token')->nullable()->comment('订阅认证 Token');
            $table->string('content_encoding', 50)->nullable()->comment('内容加密编码（如 aesgcm）');
            $table->timestamps();

            $table->index(['subscribable_type', 'subscribable_id']);
            $table->unique('endpoint');
        });
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE push_subscriptions COMMENT = 'Web Push 订阅表'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
    }
};
