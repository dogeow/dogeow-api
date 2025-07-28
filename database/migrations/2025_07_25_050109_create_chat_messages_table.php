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
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained('chat_rooms')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->text('message');
            $table->enum('message_type', ['text', 'system'])->default('text');
            $table->timestamps();
            
            // Performance indexes
            $table->index(['room_id', 'id', 'created_at'], 'idx_room_id_cursor');
            $table->fullText('message', 'idx_message_fulltext');
            $table->index(['user_id', 'created_at'], 'idx_user_messages');
            $table->index(['room_id', 'message_type', 'created_at'], 'idx_room_type_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
