<?php

namespace App\Filament\Resources\Contracts\Pages;

use App\Filament\Pages\ContractEditor;
use App\Filament\Resources\Contracts\ContractResource;
use App\Filament\Resources\Contracts\Tables\ContractsTable;
use App\Models\Contract;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ViewContract extends ViewRecord
{
    protected static string $resource = ContractResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('submitForApproval')
                ->label(__('app.action.submit_for_approval'))
                ->icon('heroicon-o-paper-airplane')
                ->color('primary')
                ->visible(fn () => $this->record->canBeSubmittedBy())
                ->requiresConfirmation()
                ->modalHeading(__('app.action.submit_for_approval'))
                ->modalDescription(__('app.message.submit_for_approval_confirm'))
                ->action(function () {
                    $this->record->update(['status' => Contract::STATUS_IN_REVIEW]);

                    Notification::make()
                        ->title(__('app.message.contract_submitted'))
                        ->success()
                        ->send();
                }),

            Action::make('approve')
                ->label(__('app.action.approve'))
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn () => $this->record->canBeApprovedBy())
                ->schema([
                    Textarea::make('comment')
                        ->label(__('app.label.comment'))
                        ->rows(3),
                ])
                ->action(function (array $data) {
                    $current = $this->record->currentApprover();
                    $current?->markApproved($data['comment'] ?? null);

                    if ($this->record->allApproved()) {
                        $this->record->update([
                            'status' => Contract::STATUS_APPROVED,
                            'signed_at' => now()->toDateString(),
                        ]);
                    }

                    Notification::make()
                        ->title(__('app.message.contract_approved'))
                        ->success()
                        ->send();
                }),

            Action::make('reject')
                ->label(__('app.action.reject'))
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn () => $this->record->canBeApprovedBy())
                ->schema([
                    Textarea::make('comment')
                        ->label(__('app.label.rejection_reason'))
                        ->required()
                        ->rows(3),
                ])
                ->action(function (array $data) {
                    $current = $this->record->currentApprover();
                    $current?->markRejected($data['comment']);

                    $this->record->update(['status' => Contract::STATUS_REJECTED]);

                    Notification::make()
                        ->title(__('app.message.contract_rejected'))
                        ->danger()
                        ->send();
                }),

            Action::make('returnForRevision')
                ->label(__('app.action.return_for_revision'))
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('warning')
                ->visible(fn () => $this->record->canBeApprovedBy())
                ->schema([
                    Textarea::make('comment')
                        ->label(__('app.label.return_reason'))
                        ->required()
                        ->rows(3),
                ])
                ->action(function (array $data) {
                    $current = $this->record->currentApprover();
                    $current?->update([
                        'comment' => $data['comment'],
                        'acted_at' => now(),
                    ]);

                    $this->record->update(['status' => Contract::STATUS_DRAFT]);

                    Notification::make()
                        ->title(__('app.message.contract_returned'))
                        ->warning()
                        ->send();
                }),

            ActionGroup::make([
                Action::make('editDocument')
                    ->label(__('app.action.edit_document'))
                    ->icon('heroicon-o-document-text')
                    ->url(fn () => ContractEditor::getUrl(['record' => $this->record]))
                    ->visible(fn () => $this->record->canBeEditedBy()),

                Action::make('downloadPdf')
                    ->label(__('app.action.download_pdf'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->modalHeading(__('app.action.download_pdf'))
                    ->modalSubmitActionLabel(__('app.action.download_pdf'))
                    ->schema([
                        Select::make('locale')
                            ->label(__('app.label.language'))
                            ->options([
                                'ru' => __('app.label.ru'),
                                'uz' => __('app.label.uz'),
                                'en' => __('app.label.en'),
                            ])
                            ->default(app()->getLocale())
                            ->required()
                            ->native(false),
                    ])
                    ->action(fn (array $data): StreamedResponse => ContractsTable::streamContractPdf(
                        $this->record,
                        $data['locale'] ?? 'ru'
                    )),

                EditAction::make()
                    ->visible(fn () => $this->record->canBeEditedBy()),

                Action::make('archive')
                    ->label(__('app.action.archive'))
                    ->icon('heroicon-o-archive-box')
                    ->color('gray')
                    ->visible(fn () => $this->record->canBeArchivedBy())
                    ->requiresConfirmation()
                    ->action(function () {
                        $this->record->update(['status' => Contract::STATUS_ARCHIVED]);

                        Notification::make()
                            ->title(__('app.message.contract_archived'))
                            ->success()
                            ->send();
                    }),

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
