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
        Schema::create('pdfs', function (Blueprint $table) {
            $table->id();
            // Add user_id at creation time to avoid separate migration
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('title')->nullable();
            $table->string('original_name');
            $table->string('file_path');
            $table->bigInteger('size')->default(0); // File size in bytes
            $table->string('status')->default('uploaded'); // Phase 2: uploaded, text_extracted, processing, ready
            $table->longText('text')->nullable();
            $table->integer('pages')->default(0);
            $table->json('meta')->nullable(); // THIS WAS MISSING IN YOUR ORIGINAL
            $table->timestamps();

            // Indexes for better query performance
            $table->index('user_id');
            $table->index('title');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pdfs');
    }
};