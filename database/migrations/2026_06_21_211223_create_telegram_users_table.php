<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pull the Telegram link out of the users row into its own table so we
     * can track per-bot data (locale, last-seen, username) without bloating
     * the user model. Backfill any existing linked chat ids in the same
     * step and drop the now-redundant users.telegram_chat_id column.
     */
    public function up(): void
    {
        Schema::create('telegram_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->unique()
                ->constrained()
                ->cascadeOnDelete();
            $table->string('chat_id')->unique();
            $table->string('username')->nullable();
            $table->string('locale', 5)->nullable();
            $table->timestamp('linked_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });

        if (Schema::hasColumn('users', 'telegram_chat_id')) {
            $rows = DB::table('users')
                ->whereNotNull('telegram_chat_id')
                ->where('telegram_chat_id', '!=', '')
                ->select('id', 'telegram_chat_id')
                ->get();

            foreach ($rows as $row) {
                DB::table('telegram_users')->insert([
                    'user_id' => $row->id,
                    'chat_id' => $row->telegram_chat_id,
                    'linked_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('telegram_chat_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'telegram_chat_id')) {
                $table->string('telegram_chat_id')->nullable();
            }
        });

        $rows = DB::table('telegram_users')->select('user_id', 'chat_id')->get();

        foreach ($rows as $row) {
            DB::table('users')
                ->where('id', $row->user_id)
                ->update(['telegram_chat_id' => $row->chat_id]);
        }

        Schema::dropIfExists('telegram_users');
    }
};
