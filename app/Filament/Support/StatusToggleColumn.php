<?php

namespace App\Filament\Support;

use Filament\Tables\Columns\ToggleColumn;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * The active/inactive status toggle shared by the reference-data tables
 * (currencies, departments, positions, order types, contacts, …). Callers add
 * ->sortable() where the column should sort.
 *
 * Editing is gated on the record's `update` policy ability (Shield's
 * `update_<resource>` permission). Without it the toggle renders read-only and
 * Filament also rejects the write server-side, so a user who lacks edit rights
 * cannot flip a status straight from the list.
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
            ->offColor('danger')
            ->disabled(fn (Model $record): bool => ! Gate::allows('update', $record));
    }
}
