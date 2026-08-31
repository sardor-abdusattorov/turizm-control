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

    public function telegramUser(): BelongsTo
    {
        return $this->belongsTo(TelegramUser::class, 'chat_id', 'chat_id');
    }

    protected function cleanText(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->text === null
            ? null
            : trim(html_entity_decode(strip_tags($this->text), ENT_QUOTES | ENT_HTML5)));
    }

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
