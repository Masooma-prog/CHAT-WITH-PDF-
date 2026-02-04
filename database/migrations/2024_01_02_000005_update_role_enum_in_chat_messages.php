<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Change ENUM values from ['user', 'bot', 'system'] to ['user', 'assistant']
        DB::statement("ALTER TABLE chat_messages MODIFY COLUMN role ENUM('user', 'assistant') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE chat_messages MODIFY COLUMN role ENUM('user', 'bot', 'system') DEFAULT 'user'");
    }
};
