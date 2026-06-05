<?php

namespace App\Filament\Resources\Contracts\Pages;

use App\Filament\Resources\Contracts\ContractResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewContract extends ViewRecord
{
    protected static string $resource = ContractResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('openEditor')
                ->label(__('app.action.open_editor'))
                ->icon('heroicon-o-pencil-square')
                ->color('primary')
                ->url(fn () => route('contracts.editor', ['contract' => $this->record]))
                ->visible(fn () => $this->record?->documentExists() && $this->record?->canBeEditedBy()),

            EditAction::make()
                ->visible(fn () => $this->record?->canBeEditedBy()),

            ...EditContract::approvalActions($this->record),
        ];
    }
}
