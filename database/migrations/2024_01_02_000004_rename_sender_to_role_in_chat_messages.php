<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            // Rename sender to role
            $table->renameColumn('sender', 'role');
            
            // Rename meta to metadata
            $table->renameColumn('meta', 'metadata');
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->renameColumn('role', 'sender');
            $table->renameColumn('metadata', 'meta');
        });
    }
};
