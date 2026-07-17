<?php

namespace App\Models\Concerns;

trait HasActiveOptions
{
    /**
     * Active records as an [id => localised label] map — the option source
     * for the selects and filters across the panel.
     *
     * @return array<int, string>
     */
    protected static function activeOptions(string $labelAttribute, string $sortColumn = 'sort'): array
    {
        return static::query()
            ->active()
            ->orderBy($sortColumn)
            ->get()
            ->mapWithKeys(fn (self $item) => [
                $item->id => $item->getTranslation($labelAttribute, app()->getLocale()),
            ])
            ->toArray();
    }
}
