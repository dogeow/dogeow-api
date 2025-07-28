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
        Schema::create('chat_moderation_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained('chat_rooms')->onDelete('cascade');
            $table->foreignId('moderator_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('target_user_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->foreignId('message_id')->nullable()->constrained('chat_messages')->onDelete('cascade');
            $table->enum('action_type', ['delete_message', 'mute_user', 'unmute_user', 'timeout_user', 'ban_user', 'unban_user']);
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable(); // Store additional data like timeout duration
            $table->timestamps();

            $table->index(['room_id', 'created_at']);
            $table->index(['moderator_id', 'created_at']);
            $table->index(['target_user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_moderation_actions');
    }
};