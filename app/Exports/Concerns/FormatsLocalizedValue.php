<?php

namespace App\Exports\Concerns;

/**
 * Resolves a locale-keyed JSON column (e.g. `['ru' => ..., 'uz' => ...]`) to
 * the current app locale for export columns that store translatable text,
 * falling back to Russian and then to whatever translation is set.
 */
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
