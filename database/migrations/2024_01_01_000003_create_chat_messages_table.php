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
            $table->foreignId('session_id')->constrained('chat_sessions')->onDelete('cascade');
            $table->enum('sender', ['user', 'bot', 'system'])->default('user');
            $table->longText('message');
            $table->json('meta')->nullable(); // Source references, embeddings metadata, etc.
            $table->timestamps();
            
            // Indexes for better query performance
            $table->index('session_id');
            $table->index(['session_id', 'created_at']);
            $table->index('sender');
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