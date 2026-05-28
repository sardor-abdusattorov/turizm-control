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

    /**
     * Translatable JSON fields come back from the model as the active
     * locale only. Re-hydrate all locales so the locale-tabbed editor
     * shows existing values for RU/UZ/EN, not just the current
     * session language.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['data'] = $this->record->getTranslations('data');
        $data['title'] = $this->record->getTranslations('title');
        $data['description'] = $this->record->getTranslations('description');

        return $data;
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
