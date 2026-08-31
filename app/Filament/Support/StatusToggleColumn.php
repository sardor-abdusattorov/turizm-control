<?php

namespace App\Filament\Support;

use Filament\Tables\Columns\ToggleColumn;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

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
