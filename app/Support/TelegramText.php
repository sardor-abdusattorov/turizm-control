<?php

namespace App\Support;

class TelegramText
{
    public static function escape(string $value): string
    {
        return str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $value);
    }
}
