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
        Schema::create('note_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->constrained('notes')->onDelete('cascade');
            $table->foreignId('target_id')->constrained('notes')->onDelete('cascade');
            $table->string('type')->nullable();
            $table->timestamps();
            
            // 唯一约束：防止重复链接
            $table->unique(['source_id', 'target_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('note_links');
    }
};
