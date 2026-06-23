<?php

namespace App\Support;

class TelegramText
{
    /**
     * Escape free-form text for a Telegram HTML message — Telegram only
     * requires &, < and > to be encoded.
     */
    public static function escape(string $value): string
    {
        return str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $value);
    }
}
