<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelegramUser extends Model
{
    protected $fillable = [
        'user_id',
        'chat_id',
        'username',
        'locale',
        'linked_at',
        'last_seen_at',
        'blocked_at',
    ];

    protected $casts = [
        'linked_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'blocked_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Whether this chat's owner has blocked the bot (Telegram answered 403) —
     * the delivery jobs skip such chats instead of burning retries on them.
     */
    public static function isBlockedChat(string $chatId): bool
    {
        return static::query()
            ->where('chat_id', $chatId)
            ->whereNotNull('blocked_at')
            ->exists();
    }
}
