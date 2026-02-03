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
        Schema::create('predefined_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pdf_id')->nullable()->constrained('pdfs')->onDelete('cascade');
            $table->string('title');
            $table->text('question');
            $table->longText('answer')->nullable(); // Optional cached answer
            $table->timestamps();
            
            // Indexes for better query performance
            $table->index('pdf_id');
            $table->index('title');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('predefined_questions');
    }
};