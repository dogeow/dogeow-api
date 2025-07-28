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
        Schema::create('chat_message_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->nullable()->constrained('chat_messages')->onDelete('set null');
            $table->foreignId('reported_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('room_id')->constrained('chat_rooms')->onDelete('cascade');
            $table->enum('report_type', [
                'inappropriate_content',
                'spam',
                'harassment',
                'hate_speech',
                'violence',
                'sexual_content',
                'misinformation',
                'other'
            ]);
            $table->text('reason')->nullable();
            $table->enum('status', ['pending', 'reviewed', 'resolved', 'dismissed'])->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->json('metadata')->nullable(); // Store additional context
            $table->timestamps();

            $table->index(['message_id', 'reported_by']); // Index for duplicate report checks
            $table->index(['status', 'created_at']);
            $table->index(['room_id', 'status']);
            $table->index(['reported_by', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_message_reports');
    }
};