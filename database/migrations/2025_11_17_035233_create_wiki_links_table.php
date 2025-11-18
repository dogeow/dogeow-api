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
        Schema::create('wiki_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->constrained('wiki_nodes')->onDelete('cascade');
            $table->foreignId('target_id')->constrained('wiki_nodes')->onDelete('cascade');
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
        Schema::dropIfExists('wiki_links');
    }
};
