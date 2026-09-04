<?php

namespace App\Filament\Support;

use Filament\Tables\Columns\TextColumn;

class UpdatedAtColumn
{
    public static function make(string $field = 'updated_at'): TextColumn
    {
        return TextColumn::make($field)
            ->label(__('app.label.updated_at'))
            ->dateTime('d.m.Y H:i')
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }
}
