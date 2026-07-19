<?php

namespace App\Filament\Support;

use Filament\Actions\Action;
use Filament\Tables\Contracts\HasTable;

/**
 * Shared shell for the "Export to Excel" buttons: one label, icon and colour
 * everywhere, and the button greys out when the current table view (with
 * filters, search and tabs applied) has nothing to export. Call sites keep
 * their own permission check and download closure — both already receive the
 * same filtered query, so the file always matches what the screen shows.
 */
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
