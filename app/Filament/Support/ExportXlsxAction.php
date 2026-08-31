<?php

namespace App\Filament\Support;

use Filament\Actions\Action;
use Filament\Tables\Contracts\HasTable;

class ExportXlsxAction
{
    public static function make(): Action
    {
        return Action::make('exportXlsx')
            ->label(__('app.action.export_xlsx'))
            ->icon('heroicon-o-arrow-down-tray')
            ->color('gray')
            ->disabled(fn (HasTable $livewire): bool => ! ($livewire->getFilteredTableQuery()?->exists() ?? false));
    }
}
