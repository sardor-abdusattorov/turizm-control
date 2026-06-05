<?php

namespace App\Filament\Resources\Contracts\Pages;

use App\Filament\Resources\Contracts\ContractResource;
use App\Models\Contract;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
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
                ->url(fn () => route('contracts.editor', ['contract' => $this->record]))
                ->visible(fn () => $this->record?->documentExists()),

            Action::make('submitForApproval')
                ->label(__('app.action.submit_for_approval'))
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading(__('app.action.submit_for_approval'))
                ->modalDescription(__('app.message.submit_for_approval_confirm'))
                ->visible(fn () => $this->record?->canBeSubmittedBy())
                ->action(function (): void {
                    $this->record->update(['status' => Contract::STATUS_IN_REVIEW]);

                    Notification::make()
                        ->title(__('app.message.submitted_for_approval'))
                        ->success()
                        ->send();

                    $this->redirect(ContractResource::getUrl('edit', ['record' => $this->record]));
                }),

            DeleteAction::make()
                ->visible(fn () => $this->record?->canBeDeletedBy()),
        ];
    }
}
