<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
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

    /**
     * The message body as a human would read it: HTML tags stripped and
     * entities decoded, so `Hello <b>team</b> &amp; co` becomes `Hello team & co`.
     */
    protected function cleanText(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->text === null
            ? null
            : trim(html_entity_decode(strip_tags($this->text), ENT_QUOTES | ENT_HTML5)));
    }

    /**
     * Telegram returns failures as a JSON blob
     * (`{"ok":false,"error_code":400,"description":"Bad Request: ..."}`). Pull
     * out just the human `description`; fall back to the raw string for
     * non-JSON transport errors (timeouts, DNS, etc.).
     */
    protected function humanError(): Attribute
    {
        return Attribute::get(function (): ?string {
            if (! $this->error) {
                return null;
            }

            $decoded = json_decode($this->error, true);

            return is_array($decoded) && isset($decoded['description'])
                ? (string) $decoded['description']
                : $this->error;
        });
    }
}
