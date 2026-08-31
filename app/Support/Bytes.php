<?php

namespace App\Support;

class Bytes
{
    public static function human(int|float|string|null $bytes): string
    {
        $bytes = max((int) $bytes, 0);

        [$value, $decimals, $unit] = $bytes >= 1024 * 1024
            ? [$bytes / 1024 / 1024, 2, 'MB']
            : [$bytes / 1024, 1, 'KB'];

        $formatted = number_format($value, $decimals, ',', ' ');

        if (str_contains($formatted, ',')) {
            $formatted = rtrim(rtrim($formatted, '0'), ',');
        }

        return $formatted.' '.$unit;
    }
}
