<?php

namespace App\Support;

class Bytes
{
    /**
     * Human-readable file size in the app's number style — thousands split by
     * spaces, comma as the decimal mark (mirroring {@see Money}), KB under a
     * megabyte and MB above, with a zero fraction dropped: «512 KB», «1,5 MB»,
     * «50 MB».
     */
    public static function human(int|float|string|null $bytes): string
    {
        $bytes = max((int) $bytes, 0);

        [$value, $decimals, $unit] = $bytes >= 1024 * 1024
            ? [$bytes / 1024 / 1024, 2, 'MB']
            : [$bytes / 1024, 1, 'KB'];

        $formatted = number_format($value, $decimals, ',', ' ');

        // Drop a trailing ",0" / ",00" so whole values stay clean.
        if (str_contains($formatted, ',')) {
            $formatted = rtrim(rtrim($formatted, '0'), ',');
        }

        return $formatted.' '.$unit;
    }
}
