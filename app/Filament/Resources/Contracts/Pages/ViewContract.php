<?php

namespace App\Filament\Resources\Contracts\Pages;

use App\Filament\Resources\Contracts\Actions\ContractWorkflowActions;
use App\Filament\Resources\Contracts\ContractResource;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewContract extends ViewRecord
{
    protected static string $resource = ContractResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ContractWorkflowActions::submitForApproval(),
            ContractWorkflowActions::approve(),
            ContractWorkflowActions::reject(),
            ContractWorkflowActions::returnForRevision(),

            ActionGroup::make([
                ContractWorkflowActions::editDocument(),
                ContractWorkflowActions::downloadPdf(),
                EditAction::make()
                    ->visible(fn () => $this->record->canBeEditedBy()),
                ContractWorkflowActions::archive(),
                DeleteAction::make()
                    ->visible(fn () => $this->record->canBeDeletedBy()),
            ])
                ->label(__('app.label.more'))
                ->icon('heroicon-m-ellipsis-vertical')
                ->button()
                ->color('gray'),
        ];
    }
}
