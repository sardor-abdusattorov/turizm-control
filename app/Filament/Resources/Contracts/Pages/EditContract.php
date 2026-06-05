<?php

namespace App\Filament\Resources\Contracts\Pages;

use App\Filament\Pages\ContractDocumentEditor;
use App\Filament\Resources\Contracts\ContractResource;
use App\Models\Contract;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditContract extends EditRecord
{
    protected static string $resource = ContractResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('openEditor')
                ->label(__('app.action.open_editor'))
                ->icon('heroicon-o-pencil-square')
                ->color('primary')
                ->url(fn () => ContractDocumentEditor::getUrl(['record' => $this->record]))
                ->visible(fn () => $this->record?->documentExists()),

            DeleteAction::make()
                ->visible(fn () => $this->record?->canBeDeletedBy()),
        ];
    }
}
