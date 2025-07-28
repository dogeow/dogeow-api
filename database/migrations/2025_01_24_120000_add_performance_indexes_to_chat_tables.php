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
        Schema::table('chat_messages', function (Blueprint $table) {
            // Composite index for cursor-based pagination
            $table->index(['room_id', 'id', 'created_at'], 'idx_room_id_cursor');
            
            // Index for message search functionality
            $table->fullText('message', 'idx_message_fulltext');
            
            // Index for user message history
            $table->index(['user_id', 'created_at'], 'idx_user_messages');
            
            // Index for message type filtering with room
            $table->index(['room_id', 'message_type', 'created_at'], 'idx_room_type_time');
        });

        Schema::table('chat_rooms', function (Blueprint $table) {
            // Index for room name searches (case-insensitive)
            $table->index(['name', 'is_active'], 'idx_name_active');
            
            // Index for room statistics queries
            $table->index(['created_by', 'is_active', 'created_at'], 'idx_creator_stats');
        });

        Schema::table('chat_room_users', function (Blueprint $table) {
            // Composite index for presence cleanup queries
            $table->index(['is_online', 'last_seen_at'], 'idx_online_cleanup');
            
            // Index for user activity tracking
            $table->index(['user_id', 'last_seen_at'], 'idx_user_activity');
            
            // Index for room statistics
            $table->index(['room_id', 'joined_at'], 'idx_room_join_stats');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropIndex('idx_room_id_cursor');
            $table->dropFullText('idx_message_fulltext');
            $table->dropIndex('idx_user_messages');
            $table->dropIndex('idx_room_type_time');
        });

        Schema::table('chat_rooms', function (Blueprint $table) {
            $table->dropIndex('idx_name_active');
            $table->dropIndex('idx_creator_stats');
        });

        Schema::table('chat_room_users', function (Blueprint $table) {
            $table->dropIndex('idx_online_cleanup');
            $table->dropIndex('idx_user_activity');
            $table->dropIndex('idx_room_join_stats');
        });
    }
};