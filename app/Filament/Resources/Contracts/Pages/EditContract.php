<?php

namespace App\Filament\Resources\Contracts\Pages;

use App\Filament\Resources\Contracts\ContractResource;
use App\Services\Contracts\ContractWorkflow;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditContract extends EditRecord
{
    protected static string $resource = ContractResource::class;

    protected function getRedirectUrl(): string
    {
        return ContractResource::getUrl('view', ['record' => $this->record]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('backToView')
                ->label(__('app.action.back_to_contract'))
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn () => ContractResource::getUrl('view', ['record' => $this->record])),

            Action::make('openEditor')
                ->label(__('app.action.open_editor'))
                ->icon('heroicon-o-pencil-square')
                ->color('primary')
                ->url(fn () => route('contracts.editor', [
                    'contract' => $this->record,
                    'mode' => 'edit',
                ]))
                ->visible(fn () => $this->record?->documentExists()),

            DeleteAction::make()
                ->visible(fn () => $this->record?->canBeDeletedBy()),
        ];
    }

    /**
     * Approve / Reject / Return — visible only to the current approver.
     * Reused by ViewContract so approvers can act without entering edit mode.
     *
     * @return array<int, Action>
     */
    public static function approvalActions(mixed $record): array
    {
        return [
            Action::make('approve')
                ->label(__('app.action.approve'))
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading(__('app.action.approve'))
                ->schema([
                    Textarea::make('comment')
                        ->label(__('app.label.comment'))
                        ->rows(3),
                ])
                ->visible(fn () => $record?->canBeApprovedBy())
                ->action(function (array $data, ContractWorkflow $workflow) use ($record): void {
                    if (! $workflow->approve($record, auth()->user(), $data['comment'] ?? null)) {
                        Notification::make()->title(__('app.message.action_not_allowed'))->danger()->send();

                        return;
                    }

                    Notification::make()->title(__('app.message.contract_approved'))->success()->send();
                }),

            Action::make('reject')
                ->label(__('app.action.reject'))
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->modalHeading(__('app.action.reject'))
                ->schema([
                    Textarea::make('comment')
                        ->label(__('app.label.rejection_reason'))
                        ->required()
                        ->rows(3),
                ])
                ->visible(fn () => $record?->canBeApprovedBy())
                ->action(function (array $data, ContractWorkflow $workflow) use ($record): void {
                    if (! $workflow->reject($record, auth()->user(), $data['comment'])) {
                        Notification::make()->title(__('app.message.action_not_allowed'))->danger()->send();

                        return;
                    }

                    Notification::make()->title(__('app.message.contract_rejected'))->danger()->send();
                }),

            Action::make('returnForRevision')
                ->label(__('app.action.return_for_revision'))
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('warning')
                ->modalHeading(__('app.action.return_for_revision'))
                ->schema([
                    Textarea::make('comment')
                        ->label(__('app.label.return_reason'))
                        ->required()
                        ->rows(3),
                ])
                ->visible(fn () => $record?->canBeApprovedBy())
                ->action(function (array $data, ContractWorkflow $workflow) use ($record): void {
                    if (! $workflow->returnForRevision($record, auth()->user(), $data['comment'])) {
                        Notification::make()->title(__('app.message.action_not_allowed'))->danger()->send();

                        return;
                    }

                    Notification::make()->title(__('app.message.contract_returned'))->warning()->send();
                }),
        ];
    }
}
