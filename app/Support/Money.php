<?php

namespace App\Support;

class Money
{
    public static function format(float|int|string|null $amount): string
    {
        $formatted = number_format((float) $amount, 2, ',', ' ');

        return str_ends_with($formatted, ',00')
            ? substr($formatted, 0, -3)
            : $formatted;
    }
}
