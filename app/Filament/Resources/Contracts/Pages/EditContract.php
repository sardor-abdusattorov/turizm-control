<?php

namespace App\Filament\Resources\Contracts\Pages;

use App\Enums\ContractStatus;
use App\Filament\Resources\Contracts\ContractResource;
use App\Filament\Resources\Contracts\Pages\Concerns\HandlesDossierUploads;
use App\Filament\Resources\Contracts\Schemas\ContractForm;
use App\Models\Contract;
use App\Models\ContractApprover;
use App\Services\Contracts\ApprovalChain;
use App\Services\Contracts\ContractWorkflow;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditContract extends EditRecord
{
    use HandlesDossierUploads;

    protected static string $resource = ContractResource::class;

    /** @var array<int, int> */
    protected array $approverChain = [];

    /** @var array<int, int> */
    protected array $originalChain = [];

    protected bool $syncChain = false;

    protected ?ContractStatus $originalStatus = null;

    protected bool $markAsSigned = false;

    protected mixed $legacySignedAt = null;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        abort_unless($this->record->canBeEditedBy(), 403);
    }

    protected function getRedirectUrl(): string
    {
        return ContractResource::getUrl('view', ['record' => $this->record]);
    }

    protected function getSaveFormAction(): Action
    {
        $keepsLegacy = fn (): bool => (bool) ($this->data['already_signed'] ?? false);
        $isMidFlow = fn (): bool => in_array($this->record?->status, [
            Contract::STATUS_IN_REVIEW,
            Contract::STATUS_APPROVED,
            Contract::STATUS_REJECTED,
        ], true);

        return Action::make('save')
            ->label(__('filament-panels::resources/pages/edit-record.form.actions.save.label'))
            ->keyBindings(['mod+s'])

            ->modal(fn (): bool => ! $keepsLegacy())
            ->requiresConfirmation()
            ->modalIcon(fn (): string => $isMidFlow() ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-question-mark-circle')
            ->modalIconColor(fn (): string => $isMidFlow() ? 'warning' : 'primary')
            ->modalHeading(__('app.label.save_changes'))
            ->modalDescription(fn (): string => $isMidFlow()
                ? __('app.message.save_warning_mid_flow')
                : __('app.message.save_warning_draft'))
            ->modalSubmitActionLabel(__('app.action.save_anyway'))
            ->modalCancelActionLabel(__('app.action.keep_editing'))
            ->modalWidth('xl')
            ->action(fn () => $this->{$this->getSubmitFormLivewireMethodName()}());
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $ids = $this->liveChainIds();

        if ($ids === [] && $this->record->status === Contract::STATUS_DRAFT) {
            $ids = ContractForm::defaultApproverIds();
        }

        $data['approver_chain'] = $ids;

        $data['already_signed'] = $this->record->status === Contract::STATUS_APPROVED;

        $attachments = $this->record->attachments()->orderBy('sort')->get();
        $data['attachment_files'] = $attachments->pluck('file_path')->all();
        $data['attachment_names'] = $attachments->pluck('original_name', 'file_path')->all();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->markAsSigned = (bool) ($data['already_signed'] ?? false);
        $this->legacySignedAt = $data['signed_at'] ?? now();
        unset($data['already_signed'], $data['signed_at']);

        if ($this->markAsSigned) {
            $this->record->preserveApprovedOnNextSave();
        }

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

        return $this->extractAttachmentUploads($data);
    }

    protected function afterSave(): void
    {
        $this->storeFormAttachments();

        if ($this->markAsSigned) {
            $this->record->approvers()
                ->whereIn('status', [ContractApprover::STATUS_QUEUED, ContractApprover::STATUS_PENDING])
                ->update([
                    'status' => ContractApprover::STATUS_INVALIDATED,
                    'system_comment' => 'invalidated_on_edit',
                    'acted_at' => now(),
                ]);

            $this->record->forceFill([
                'status' => Contract::STATUS_APPROVED,
                'signed_at' => $this->legacySignedAt,
            ])->saveQuietly();

            return;
        }

        $startedMidFlow = $this->originalStatus !== null
            && $this->originalStatus !== Contract::STATUS_DRAFT;

        $chainChanged = $this->syncChain
            && $this->approverChain !== $this->originalChain;

        if (! $chainChanged && ! $startedMidFlow) {
            return;
        }

        $observerHandledReset = $startedMidFlow
            && $this->record->status === Contract::STATUS_DRAFT;

        if (! $chainChanged && $observerHandledReset) {
            return;
        }

        app(ApprovalChain::class)->resyncOnEdit(
            $this->record,
            $chainChanged ? $this->approverChain : $this->originalChain,
            cancelDecided: $startedMidFlow && ! $observerHandledReset,
        );
    }

    /** @return array<int, int> */
    protected function liveChainIds(): array
    {
        return $this->record->activeApprovers()
            ->pluck('user_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('backToView')
                ->label(__('app.action.back_to_contract'))
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn () => ContractResource::getUrl('view', ['record' => $this->record])),

            DeleteAction::make()
                ->visible(fn () => $this->record?->canBeDeletedBy()),
        ];
    }

    /** @return array<int, Action> */
    public static function approvalActions(mixed $record): array
    {
        return [
            Action::make('approve')
                ->label(__('app.action.approve'))
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalIcon('heroicon-o-check-circle')
                ->modalHeading(__('app.action.approve'))
                ->modalSubmitActionLabel(__('app.action.approve'))
                ->modalWidth('xl')
                ->schema([
                    Textarea::make('comment')
                        ->label(__('app.label.comment'))
                        ->rows(4),
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
                ->requiresConfirmation()
                ->modalIcon('heroicon-o-x-circle')
                ->modalHeading(__('app.action.reject'))
                ->modalSubmitActionLabel(__('app.action.reject'))
                ->modalWidth('xl')
                ->schema([
                    Textarea::make('comment')
                        ->label(__('app.label.rejection_reason'))
                        ->required()
                        ->rows(4),
                ])
                ->visible(fn () => $record?->canBeApprovedBy())
                ->action(function (array $data, ContractWorkflow $workflow) use ($record): void {
                    if (! $workflow->reject($record, auth()->user(), $data['comment'])) {
                        Notification::make()->title(__('app.message.action_not_allowed'))->danger()->send();

                        return;
                    }

                    Notification::make()->title(__('app.message.contract_rejected'))->danger()->send();
                }),
        ];
    }
}
