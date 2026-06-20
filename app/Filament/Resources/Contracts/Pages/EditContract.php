<?php

namespace App\Filament\Resources\Contracts\Pages;

use App\Enums\ContractStatus;
use App\Filament\Resources\Contracts\ContractResource;
use App\Filament\Resources\Contracts\Schemas\ContractForm;
use App\Models\Contract;
use App\Models\ContractApprover;
use App\Services\Contracts\ContractWorkflow;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditContract extends EditRecord
{
    protected static string $resource = ContractResource::class;

    /** @var array<int, int> */
    protected array $approverChain = [];

    /**
     * The live chain (active approver user IDs, in order) BEFORE save — used
     * to tell whether the author actually changed the chain on this edit.
     *
     * @var array<int, int>
     */
    protected array $originalChain = [];

    protected bool $syncChain = false;

    protected ?ContractStatus $originalStatus = null;

    protected function getRedirectUrl(): string
    {
        return ContractResource::getUrl('view', ['record' => $this->record]);
    }

    protected function getSaveFormAction(): Action
    {
        $isMidFlow = $this->record && in_array($this->record->status, [
            Contract::STATUS_IN_REVIEW,
            Contract::STATUS_APPROVED,
            Contract::STATUS_REJECTED,
        ], true);

        return Action::make('save')
            ->label(__('filament-panels::resources/pages/edit-record.form.actions.save.label'))
            ->keyBindings(['mod+s'])
            ->requiresConfirmation()
            ->modalIcon($isMidFlow ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-question-mark-circle')
            ->modalIconColor($isMidFlow ? 'warning' : 'primary')
            ->modalHeading(__('app.label.save_changes'))
            ->modalDescription($isMidFlow
                ? __('app.message.save_warning_mid_flow')
                : __('app.message.save_warning_draft'))
            ->modalSubmitActionLabel(__('app.action.save_anyway'))
            ->modalCancelActionLabel(__('app.action.keep_editing'))
            ->action(fn () => $this->{$this->getSubmitFormLivewireMethodName()}());
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $ids = $this->liveChainIds();

        if ($ids === [] && $this->record->status === Contract::STATUS_DRAFT) {
            $ids = ContractForm::defaultApproverIds();
        }

        $data['approver_chain'] = $ids;

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->originalStatus = $this->record->status;
        $this->originalChain = $this->liveChainIds();
        $this->syncChain = array_key_exists('approver_chain', $data);
        $this->approverChain = collect((array) ($data['approver_chain'] ?? []))
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();
        unset($data['approver_chain']);

        return $data;
    }

    protected function afterSave(): void
    {
        if (! $this->syncChain || $this->approverChain === $this->originalChain) {
            return;
        }

        if ($this->originalStatus !== Contract::STATUS_DRAFT
            && $this->record->status !== Contract::STATUS_DRAFT) {
            $this->record->forceFill([
                'status' => Contract::STATUS_DRAFT,
                'signed_at' => null,
            ])->saveQuietly();
            $this->record->invalidateAllApprovers(__('app.message.invalidated_on_edit'));
        }

        $this->record->approvers()
            ->whereIn('status', [ContractApprover::STATUS_QUEUED, ContractApprover::STATUS_PENDING])
            ->update([
                'status' => ContractApprover::STATUS_INVALIDATED,
                'system_comment' => __('app.message.invalidated_on_edit'),
                'acted_at' => now(),
            ]);

        $this->rebuildQueuedChain();
    }

    /**
     * Active approver user IDs (excludes invalidated/skipped), in chain order.
     *
     * @return array<int, int>
     */
    protected function liveChainIds(): array
    {
        return $this->record->activeApprovers()
            ->pluck('user_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    protected function rebuildQueuedChain(): void
    {
        $order = 1;

        foreach ($this->approverChain as $userId) {
            ContractApprover::create([
                'contract_id' => $this->record->id,
                'user_id' => $userId,
                'order' => $order++,
                'status' => ContractApprover::STATUS_QUEUED,
            ]);
        }
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
                ->color('gray')
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
