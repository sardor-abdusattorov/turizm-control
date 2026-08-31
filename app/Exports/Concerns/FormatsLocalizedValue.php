<?php

namespace App\Exports\Concerns;

trait FormatsLocalizedValue
{
    private static function localized(mixed $value): ?string
    {
        if (is_array($value)) {
            return $value[app()->getLocale()] ?? $value['ru'] ?? (reset($value) ?: null);
        }

        return $value;
    }
}
