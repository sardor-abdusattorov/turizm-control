<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marks a linked chat whose owner blocked the bot (Telegram answers 403).
 * Reminders and broadcasts skip blocked chats; the mark clears itself the
 * moment the user talks to the bot again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_users', function (Blueprint $table) {
            $table->timestamp('blocked_at')->nullable()->after('last_seen_at');
        });
    }

    public function down(): void
    {
        Schema::table('telegram_users', function (Blueprint $table) {
            $table->dropColumn('blocked_at');
        });
    }
};
