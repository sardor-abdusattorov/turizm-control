<?php

namespace App\Filament\Resources\Contracts\Actions;

use App\Filament\Pages\ContractEditor;
use App\Filament\Resources\Contracts\ContractResource;
use App\Filament\Resources\Contracts\Tables\ContractsTable;
use App\Models\Contract;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Actions\Action as NotificationAction;
use Filament\Notifications\Notification;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Shared definitions for every contract workflow action — used by
 * both the list-table row menu and the View/Edit page header so the
 * gating, modals, notifications, and side effects stay in one
 * place. Each method returns a fresh Action instance; never reuse
 * a single instance across contexts (Filament caches state on it).
 */
class ContractWorkflowActions
{
    /**
     * Every workflow action, in display order. Useful for dropping
     * the whole bundle into a table's recordActions or a page's
     * headerActions.
     *
     * @return array<int, Action>
     */
    public static function all(): array
    {
        return [
            self::submitForApproval(),
            self::approve(),
            self::reject(),
            self::returnForRevision(),
            self::archive(),
            self::editDocument(),
            self::downloadPdf(),
        ];
    }

    public static function submitForApproval(): Action
    {
        return Action::make('submitForApproval')
            ->label(__('app.action.submit_for_approval'))
            ->icon('heroicon-o-paper-airplane')
            ->color('primary')
            ->visible(fn (Contract $record): bool => $record->canBeSubmittedBy())
            ->requiresConfirmation()
            ->modalHeading(__('app.action.submit_for_approval'))
            ->modalDescription(__('app.message.submit_for_approval_confirm'))
            ->action(function (Contract $record): void {
                $record->update(['status' => Contract::STATUS_IN_REVIEW]);

                self::notifyApprover($record);

                Notification::make()
                    ->title(__('app.message.contract_submitted'))
                    ->success()
                    ->send();
            });
    }

    public static function approve(): Action
    {
        return Action::make('approve')
            ->label(__('app.action.approve'))
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->visible(fn (Contract $record): bool => $record->canBeApprovedBy())
            ->modalHeading(__('app.action.approve'))
            ->schema([
                Textarea::make('comment')
                    ->label(__('app.label.comment'))
                    ->rows(3),
            ])
            ->action(function (array $data, Contract $record): void {
                $record->currentApprover()?->markApproved($data['comment'] ?? null);

                $record->refresh();

                if ($record->allApproved()) {
                    $record->update([
                        'status' => Contract::STATUS_APPROVED,
                        'signed_at' => now()->toDateString(),
                    ]);

                    self::notifyResponsible(
                        $record,
                        __('app.notification.contract_approved.title'),
                        __('app.notification.contract_approved.body', ['number' => $record->number]),
                        'success',
                    );
                } else {
                    self::notifyApprover($record);
                }

                Notification::make()
                    ->title(__('app.message.contract_approved'))
                    ->success()
                    ->send();
            });
    }

    public static function reject(): Action
    {
        return Action::make('reject')
            ->label(__('app.action.reject'))
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (Contract $record): bool => $record->canBeApprovedBy())
            ->modalHeading(__('app.action.reject'))
            ->schema([
                Textarea::make('comment')
                    ->label(__('app.label.rejection_reason'))
                    ->required()
                    ->rows(3),
            ])
            ->action(function (array $data, Contract $record): void {
                $record->currentApprover()?->markRejected($data['comment']);
                $record->update(['status' => Contract::STATUS_REJECTED]);

                self::notifyResponsible(
                    $record,
                    __('app.notification.contract_rejected.title'),
                    __('app.notification.contract_rejected.body', [
                        'number' => $record->number,
                        'reason' => $data['comment'],
                    ]),
                    'danger',
                );

                Notification::make()
                    ->title(__('app.message.contract_rejected'))
                    ->danger()
                    ->send();
            });
    }

    public static function returnForRevision(): Action
    {
        return Action::make('returnForRevision')
            ->label(__('app.action.return_for_revision'))
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('warning')
            ->visible(fn (Contract $record): bool => $record->canBeApprovedBy())
            ->modalHeading(__('app.action.return_for_revision'))
            ->schema([
                Textarea::make('comment')
                    ->label(__('app.label.return_reason'))
                    ->required()
                    ->rows(3),
            ])
            ->action(function (array $data, Contract $record): void {
                $record->currentApprover()?->update([
                    'comment' => $data['comment'],
                    'acted_at' => now(),
                ]);

                $record->update(['status' => Contract::STATUS_DRAFT]);

                self::notifyResponsible(
                    $record,
                    __('app.notification.contract_returned.title'),
                    __('app.notification.contract_returned.body', [
                        'number' => $record->number,
                        'reason' => $data['comment'],
                    ]),
                    'warning',
                );

                Notification::make()
                    ->title(__('app.message.contract_returned'))
                    ->warning()
                    ->send();
            });
    }

    public static function archive(): Action
    {
        return Action::make('archive')
            ->label(__('app.action.archive'))
            ->icon('heroicon-o-archive-box')
            ->color('gray')
            ->visible(fn (Contract $record): bool => $record->canBeArchivedBy())
            ->requiresConfirmation()
            ->action(function (Contract $record): void {
                $record->update(['status' => Contract::STATUS_ARCHIVED]);

                Notification::make()
                    ->title(__('app.message.contract_archived'))
                    ->success()
                    ->send();
            });
    }

    public static function editDocument(): Action
    {
        return Action::make('editDocument')
            ->label(__('app.action.edit_document'))
            ->icon('heroicon-o-document-text')
            ->url(fn (Contract $record): string => ContractEditor::getUrl(['record' => $record]))
            ->visible(fn (Contract $record): bool => $record->canBeEditedBy());
    }

    public static function downloadPdf(): Action
    {
        return Action::make('downloadPdf')
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
            ->action(fn (array $data, Contract $record): StreamedResponse => ContractsTable::streamContractPdf(
                $record,
                $data['locale'] ?? 'ru',
            ));
    }

    /**
     * Drop a "this contract is waiting on you" notification in the
     * current approver's database bell. Quietly no-ops when the
     * chain is over (no pending approver) or the row is orphaned
     * (deleted user).
     */
    protected static function notifyApprover(Contract $record): void
    {
        $current = $record->currentApprover();
        $user = $current?->user;

        if (! $user instanceof User) {
            return;
        }

        Notification::make()
            ->title(__('app.notification.approval_requested.title'))
            ->body(__('app.notification.approval_requested.body', ['number' => $record->number]))
            ->icon('heroicon-o-clipboard-document-check')
            ->iconColor('warning')
            ->actions([
                NotificationAction::make('view')
                    ->label(__('app.action.open_contract'))
                    ->url(ContractResource::getUrl('view', ['record' => $record]))
                    ->markAsRead(),
            ])
            ->sendToDatabase($user);
    }

    /**
     * Notify the manager who owns the contract — used for outcomes
     * (approved / rejected / returned) so they can act on the
     * result without polling the list page.
     */
    protected static function notifyResponsible(
        Contract $record,
        string $title,
        string $body,
        string $color = 'primary',
    ): void {
        $user = $record->responsible;

        if (! $user instanceof User) {
            return;
        }

        Notification::make()
            ->title($title)
            ->body($body)
            ->iconColor($color)
            ->actions([
                NotificationAction::make('view')
                    ->label(__('app.action.open_contract'))
                    ->url(ContractResource::getUrl('view', ['record' => $record]))
                    ->markAsRead(),
            ])
            ->sendToDatabase($user);
    }
}
