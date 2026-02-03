<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check if column already exists before adding
        if (!Schema::hasColumn('predefined_questions', 'source')) {
            Schema::table('predefined_questions', function (Blueprint $table) {
                $table->string('source')->default('laravel_ai')->after('question');
            });
            
            // Add index separately to avoid issues
            Schema::table('predefined_questions', function (Blueprint $table) {
                $table->index('source');
            });
            
            echo "✅ Added 'source' column to predefined_questions table\n";
        } else {
            echo "ℹ️  'source' column already exists, skipping\n";
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('predefined_questions', 'source')) {
            Schema::table('predefined_questions', function (Blueprint $table) {
                $table->dropIndex(['source']);
                $table->dropColumn('source');
            });
        }
    }
};