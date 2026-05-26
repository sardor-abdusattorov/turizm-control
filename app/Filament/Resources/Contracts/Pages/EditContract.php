<?php

namespace App\Filament\Resources\Contracts\Pages;

use App\Filament\Resources\Contracts\ContractResource;
use App\Models\Contract;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditContract extends EditRecord
{
    protected static string $resource = ContractResource::class;

    /**
     * Snapshot of the status before mutateFormDataBeforeSave runs, so
     * afterSave knows whether the contract was already in review and
     * needs its approval state reset.
     */
    protected ?string $originalStatus = null;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn () => $this->record?->canBeDeletedBy()),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->originalStatus = $this->record->status;

        return $data;
    }

    protected function afterSave(): void
    {
        if (! in_array($this->originalStatus, [
            Contract::STATUS_IN_REVIEW,
            Contract::STATUS_REJECTED,
        ], true)) {
            return;
        }

        $this->record->resetApprovalState();

        Notification::make()
            ->title(__('app.message.approval_state_reset'))
            ->warning()
            ->send();
    }
}
