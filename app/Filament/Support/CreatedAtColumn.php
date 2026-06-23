<?php

namespace App\Filament\Support;

use Filament\Tables\Columns\TextColumn;

/**
 * The "created at" timestamp column shared by the reference-data tables.
 * Callers add ->toggleable(...) where the column should be hideable.
 */
class CreatedAtColumn
{
    public static function make(string $field = 'created_at'): TextColumn
    {
        return TextColumn::make($field)
            ->label(__('app.label.created_at'))
            ->dateTime('d.m.Y H:i')
            ->sortable();
    }
}
