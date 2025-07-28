<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('chat_room_users', function (Blueprint $table) {
            $table->boolean('is_muted')->default(false)->after('is_online');
            $table->timestamp('muted_until')->nullable()->after('is_muted');
            $table->boolean('is_banned')->default(false)->after('muted_until');
            $table->timestamp('banned_until')->nullable()->after('is_banned');
            $table->foreignId('muted_by')->nullable()->constrained('users')->onDelete('set null')->after('banned_until');
            $table->foreignId('banned_by')->nullable()->constrained('users')->onDelete('set null')->after('muted_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chat_room_users', function (Blueprint $table) {
            $table->dropForeign(['muted_by']);
            $table->dropForeign(['banned_by']);
            $table->dropColumn([
                'is_muted',
                'muted_until',
                'is_banned',
                'banned_until',
                'muted_by',
                'banned_by'
            ]);
        });
    }
};