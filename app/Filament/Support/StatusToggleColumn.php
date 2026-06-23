<?php

namespace App\Filament\Support;

use Filament\Tables\Columns\ToggleColumn;

/**
 * The active/inactive status toggle shared by the reference-data tables
 * (currencies, departments, positions, order types, contacts, …). Callers add
 * ->sortable() where the column should sort.
 */
class StatusToggleColumn
{
    public static function make(string $field = 'status'): ToggleColumn
    {
        return ToggleColumn::make($field)
            ->label(__('app.label.status'))
            ->onIcon('heroicon-m-check-circle')
            ->offIcon('heroicon-m-x-circle')
            ->onColor('success')
            ->offColor('danger');
    }
}
