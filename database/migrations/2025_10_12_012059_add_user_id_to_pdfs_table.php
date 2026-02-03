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
        Schema::table('pdfs', function (Blueprint $table) {
            // Add the user_id column after the 'id' column
            $table->foreignId('user_id')->after('id')->constrained()->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pdfs', function (Blueprint $table) {
            // Drop the foreign key constraint and the column
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });
    }
};

