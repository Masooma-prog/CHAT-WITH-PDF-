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
        Schema::table('chat_sessions', function (Blueprint $table) {
            // Add user_id column
            $table->foreignId('user_id')->after('id')->constrained()->onDelete('cascade');
            
            // Add last_message_at column
            $table->timestamp('last_message_at')->nullable()->after('title');
            
            // Make title nullable (auto-generated)
            $table->string('title')->nullable()->change();
            
            // Add index for user_id
            $table->index('user_id');
            $table->index('last_message_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chat_sessions', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn(['user_id', 'last_message_at']);
            $table->string('title')->nullable(false)->change();
            $table->dropIndex(['user_id']);
            $table->dropIndex(['last_message_at']);
        });
    }
};
