<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelegramMessageLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'chat_id',
        'method',
        'text',
        'ok',
        'status',
        'error',
    ];

    protected $casts = [
        'ok' => 'boolean',
        'created_at' => 'datetime',
    ];

    /** The Telegram account the message was sent to (if still linked). */
    public function telegramUser(): BelongsTo
    {
        return $this->belongsTo(TelegramUser::class, 'chat_id', 'chat_id');
    }
}
