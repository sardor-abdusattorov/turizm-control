<?php

namespace App\Support;

class Money
{
    /**
     * The one money format of the app: thousands split by spaces, comma as
     * the decimal mark, and decimals shown only when they carry information —
     * «15 000 000 UZS» stays clean while «16 595,41 EUR» keeps its cents.
     */
    public static function format(float|int|string|null $amount): string
    {
        $formatted = number_format((float) $amount, 2, ',', ' ');

        return str_ends_with($formatted, ',00')
            ? substr($formatted, 0, -3)
            : $formatted;
    }
}
