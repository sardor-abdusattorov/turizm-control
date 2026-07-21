<?php

namespace App\Filament\Resources\Contracts\Pages;

use App\Enums\PaymentStatus;
use App\Filament\Resources\Contracts\ContractResource;
use App\Filament\Support\ExportPermission;
use App\Filament\Support\PaymentFilesUpload;
use App\Models\Contract;
use App\Models\ContractApprover;
use App\Models\ContractAttachment;
use App\Models\Payment;
use App\Models\User;
use App\Rules\PaymentWithinRemaining;
use App\Services\Contracts\ContractWorkflow;
use App\Services\Payments\RecordPayment;
use App\Support\Bytes;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;

class ViewContract extends ViewRecord
{
    protected static string $resource = ContractResource::class;

    protected string $view = 'filament.resources.contracts.pages.view-contract';

    public function mount(int|string $record): void
    {
        parent::mount($record);

        // The chain timeline and payment list walk these relations per row —
        // load them once instead of a query per approver/payment (N+1).
        $this->record->loadMissing([
            'approvers.user.department',
            'approvers.user.position',
            'activeApprovers.user.department',
            'activeApprovers.user.position',
            'payments.creator',
        ]);
    }

    public function getHeading(): string
    {
        return $this->record->number;
    }

    public function getSubheading(): ?string
    {
        return null;
    }

    public function submittedAt(): ?Carbon
    {
        $created = Activity::query()
            ->where('subject_type', $this->record->getMorphClass())
            ->where('subject_id', $this->record->getKey())
            ->where('event', 'Contract Submitted')
            ->latest('id')
            ->value('created_at');

        return $created ? Carbon::parse($created) : null;
    }

    /**
     * Human-friendly time-remaining string for the current step (or null
     * when the contract isn't in review).
     *
     * @return array{label: string, overdue: bool}|null
     */
    public function timeRemaining(): ?array
    {
        // Both review stages — the director's SLA must surface here too.
        $inReview = in_array($this->record->status, [
            Contract::STATUS_IN_REVIEW,
            Contract::STATUS_IN_REVIEW_DIRECTOR,
        ], true);

        if (! $inReview) {
            return null;
        }

        $due = $this->record->currentApprover()?->due_at;

        if (! $due) {
            return null;
        }

        $now = now();
        $overdue = $now->greaterThan($due);

        return [
            'label' => $due->diffForHumans($now, ['parts' => 2, 'syntax' => CarbonInterface::DIFF_ABSOLUTE]),
            'overdue' => $overdue,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('submitForApproval')
                ->label(__('app.action.submit_for_approval'))
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading(__('app.action.submit_for_approval'))
                ->modalDescription(__('app.message.submit_for_approval_confirm'))
                ->visible(fn () => $this->record?->canBeSubmittedBy())
                ->action(function (ContractWorkflow $workflow): void {
                    if (! $workflow->submit($this->record)) {
                        Notification::make()->title(__('app.message.action_not_allowed'))->danger()->send();

                        return;
                    }

                    Notification::make()->title(__('app.message.submitted_for_approval'))->success()->send();
                    $this->refreshFormData(['status']);
                }),

            Action::make('sendToDirector')
                ->label(__('app.action.send_to_director'))
                ->icon('heroicon-o-arrow-up-circle')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading(__('app.action.send_to_director'))
                ->modalDescription(__('app.message.send_to_director_confirm'))
                ->visible(fn () => $this->record?->canBeSentToDirectorBy())
                ->action(function (ContractWorkflow $workflow): void {
                    if (! $workflow->submitToDirector($this->record)) {
                        Notification::make()->title(__('app.message.action_not_allowed'))->danger()->send();

                        return;
                    }

                    Notification::make()->title(__('app.message.sent_to_director'))->success()->send();
                    $this->refreshFormData(['status']);
                }),

            Action::make('returnToWork')
                ->label(__('app.action.return_to_work'))
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading(__('app.action.return_to_work'))
                ->modalDescription(__('app.message.return_to_work_confirm'))
                ->visible(fn (): bool => $this->record?->status === Contract::STATUS_REJECTED
                    && $this->record->canBeEditedBy())
                ->action(function (ContractWorkflow $workflow): void {
                    if (! $workflow->returnToWork($this->record)) {
                        Notification::make()->title(__('app.message.action_not_allowed'))->danger()->send();

                        return;
                    }

                    Notification::make()->title(__('app.message.returned_to_work'))->success()->send();
                    $this->refreshFormData(['status']);
                }),

            Action::make('reassignApprover')
                ->label(__('app.action.reassign_approver'))
                ->icon('heroicon-o-arrow-path-rounded-square')
                ->color('gray')
                ->modalHeading(__('app.action.reassign_approver'))
                ->modalDescription(__('app.message.reassign_approver_confirm'))
                ->visible(fn (): bool => (bool) auth()->user()?->hasRole('super_admin')
                    && in_array($this->record?->status, [Contract::STATUS_IN_REVIEW, Contract::STATUS_IN_REVIEW_DIRECTOR], true)
                    && $this->record->currentApprover() !== null)
                ->schema([
                    Select::make('user_id')
                        ->label(__('app.label.new_approver'))
                        ->options(fn (): array => User::approverOptionsGroupedByDepartment(
                            excludeId: $this->record->currentApprover()?->user_id,
                        ))
                        ->searchable()
                        ->required(),
                ])
                ->action(function (array $data, ContractWorkflow $workflow): void {
                    $newApprover = User::find($data['user_id']);

                    if (! $newApprover || ! $workflow->reassignCurrentApprover($this->record, $newApprover)) {
                        Notification::make()->title(__('app.message.action_not_allowed'))->danger()->send();

                        return;
                    }

                    Notification::make()->title(__('app.message.approver_reassigned_done'))->success()->send();
                    $this->refreshFormData(['status']);
                }),

            ...EditContract::approvalActions($this->record),

            Action::make('addPayment')
                ->label(__('app.action.add_payment'))
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->visible(fn (): bool => $this->record?->canAcceptPayment()
                    && auth()->user()?->can('create_payment'))
                ->modalHeading(__('app.action.record_payment'))
                ->schema([
                    TextInput::make('percent')
                        ->label(__('app.label.percent'))
                        ->numeric()
                        ->required()
                        ->minValue(0.01)
                        ->maxValue(fn (): float => max(0.01, $this->record->remainingPercent()))
                        ->rule(fn () => new PaymentWithinRemaining($this->record))
                        ->step('0.01')
                        ->suffix('%')
                        ->helperText(fn (): string => __('app.label.remaining_to_pay', [
                            'percent' => format_percent($this->record->remainingPercent()),
                        ])),

                    DatePicker::make('paid_at')
                        ->label(__('app.label.paid_at'))
                        ->required()
                        ->native(false)
                        ->maxDate(now())
                        ->default(now()),

                    PaymentFilesUpload::make(),
                ])
                ->action(function (array $data): void {
                    if (! app(RecordPayment::class)->record($this->record, $data)) {
                        Notification::make()->title(__('app.message.action_not_allowed'))->danger()->send();

                        return;
                    }

                    Notification::make()->title(__('app.message.payment_recorded'))->success()->send();
                    $this->record->refresh();
                }),

            Action::make('markSigned')
                ->label(__('app.action.mark_signed'))
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading(__('app.action.mark_signed'))
                ->modalDescription(__('app.message.mark_signed_confirm'))
                ->schema([
                    DatePicker::make('signed_at')
                        ->label(__('app.label.signing_date'))
                        ->default(today())
                        ->required(),
                ])
                // Only offered when the approval flow is switched off: the
                // paper was signed outside the system, filing it is enough.
                ->visible(fn (): bool => ! ContractWorkflow::approvalEnabled()
                    && $this->record->status === Contract::STATUS_DRAFT
                    && $this->canManageAttachments())
                ->action(function (array $data): void {
                    $this->record->update([
                        'status' => Contract::STATUS_APPROVED,
                        'signed_at' => $data['signed_at'],
                    ]);

                    Notification::make()->title(__('app.message.marked_signed'))->success()->send();
                }),

            EditAction::make()
                ->icon('heroicon-o-pencil-square')
                ->visible(fn () => $this->record?->canBeEditedBy()),
        ];
    }

    /**
     * Dossier scans of this contract, cached for the render pass (the tab
     * badge and the list both read it).
     *
     * @return Collection<int, ContractAttachment>
     */
    public function attachments(): Collection
    {
        return $this->cachedAttachments ??= $this->record->attachments()->with('uploader')->get();
    }

    private ?Collection $cachedAttachments = null;

    public function canManageAttachments(): bool
    {
        return $this->record->attachmentsManageableBy();
    }

    /**
     * Per-approver detail as a NATIVE Filament modal — the same chrome the
     * breakdown modals use, replacing the old hand-rolled Alpine overlay.
     * Mounted with { user: <user_id> }; shows every record that person has
     * on the contract (current + invalidated attempts).
     */
    public function approverDetailsAction(): Action
    {
        $user = fn (array $arguments): ?User => User::query()
            ->with(['department', 'position'])
            ->find((int) ($arguments['user'] ?? 0));

        return Action::make('approverDetails')
            ->modalHeading(fn (array $arguments): string => $user($arguments)?->name ?? '')
            ->modalDescription(function (array $arguments) use ($user): ?string {
                $u = $user($arguments);

                return $u ? trim(($u->department?->name ?? '').($u->position?->name ? ' · '.$u->position->name : ''), ' ·') ?: null : null;
            })
            ->modalIcon('heroicon-o-user-circle')
            ->modalWidth('3xl')
            ->modalContent(fn (array $arguments) => view(
                'filament.resources.contracts.pages.view-contract.approver-details',
                [
                    'record' => $this->record,
                    'page' => $this,
                    'userId' => (int) ($arguments['user'] ?? 0),
                ],
            ))
            ->modalSubmitAction(false)
            ->modalCancelAction(false);
    }

    public static function userCanExportContract(): bool
    {
        return ExportPermission::allows('export_contract');
    }

    public function getActivities(): Collection
    {
        return Activity::query()
            ->where('subject_type', $this->record->getMorphClass())
            ->where('subject_id', $this->record->getKey())
            ->with('causer')
            ->latest()
            ->limit(60)
            ->get();
    }

    public function documentSizeLabel(): ?string
    {
        if (! $this->record->documentExists()) {
            return null;
        }

        return Bytes::human(Storage::disk('local')->size($this->record->documentPath()));
    }

    public function pdfPreviewUrl(): ?string
    {
        if (! $this->record->documentExists()) {
            return null;
        }

        if ($this->record->status !== Contract::STATUS_APPROVED) {
            return null;
        }

        return route('contracts.pdf.inline', ['contract' => $this->record]);
    }

    public function editorUrl(string $mode = 'view'): string
    {
        return route('contracts.editor', ['contract' => $this->record, 'mode' => $mode]);
    }

    /**
     * Payment progress summary for the View page — used by the Payments
     * section in the blade view.
     *
     * @return array{
     *     payments: Collection<int, Payment>,
     *     paid_percent: float,
     *     remaining_percent: float,
     *     status: PaymentStatus,
     *     can_add: bool,
     * }
     */
    public function paymentSummary(): array
    {
        $paid = (float) $this->record->paid_percent;

        return [
            'payments' => $this->record->payments,
            'paid_percent' => $paid,
            'remaining_percent' => $this->record->remainingPercent(),
            'status' => $this->record->payment_status ?? PaymentStatus::NotPaid,
            'can_add' => $this->record->canAcceptPayment()
                && (bool) auth()->user()?->can('create_payment'),
        ];
    }

    /**
     * @return list<array{url: string, name: string, pdf: bool}>
     */
    public function paymentScreenshotFiles(Payment $payment): array
    {
        return $payment->screenshotFiles();
    }

    public function approverAvatar(ContractApprover $approver): string
    {
        return $approver->user?->avatarUrl()
            ?? 'https://ui-avatars.com/api/?name=%3F&background=E0E7FF&color=4338CA&size=80';
    }

    /**
     * @return array{icon: string, color: string}
     */
    public function approverVisual(ContractApprover $approver): array
    {
        if ($approver->status === ContractApprover::STATUS_APPROVED) {
            return ['icon' => 'heroicon-s-check-circle', 'color' => 'success'];
        }

        if ($approver->status === ContractApprover::STATUS_REJECTED) {
            return ['icon' => 'heroicon-s-x-circle', 'color' => 'danger'];
        }

        if ($this->isCurrentApprover($approver)) {
            return ['icon' => 'heroicon-s-clock', 'color' => 'primary'];
        }

        return ['icon' => 'heroicon-o-minus-circle', 'color' => 'gray'];
    }

    public function isCurrentApprover(ContractApprover $approver): bool
    {
        return $this->record->currentApprover()?->id === $approver->id;
    }

    /**
     * Headline context for the hero: a status-aware one-liner plus the SLA
     * state of the step currently in review.
     *
     * @return array{message: string, overdue: bool, due: ?Carbon}
     */
    public function heroContext(): array
    {
        $contract = $this->record;
        $current = $contract->currentApprover();

        return match ($contract->status) {
            Contract::STATUS_DRAFT => [
                'message' => __('app.message.hero_draft'),
                'overdue' => false,
                'due' => null,
            ],
            Contract::STATUS_IN_REVIEW, Contract::STATUS_IN_REVIEW_DIRECTOR => [
                'message' => $current?->user
                    ? __('app.message.hero_in_review', ['name' => $current->user->name])
                    : __('app.message.hero_in_review_generic'),
                'overdue' => (bool) $current?->isOverdue(),
                'due' => $current?->due_at,
            ],
            Contract::STATUS_PENDING_DIRECTOR => [
                'message' => __('app.message.hero_pending_director'),
                'overdue' => false,
                'due' => null,
            ],
            Contract::STATUS_APPROVED => [
                'message' => $contract->signed_at
                    ? __('app.message.hero_approved', ['date' => $contract->signed_at->format('d.m.Y')])
                    : __('app.message.hero_approved_generic'),
                'overdue' => false,
                'due' => null,
            ],
            Contract::STATUS_REJECTED => [
                'message' => __('app.message.hero_rejected'),
                'overdue' => false,
                'due' => null,
            ],
            default => ['message' => '', 'overdue' => false, 'due' => null],
        };
    }

    public function approverState(ContractApprover $approver): string
    {
        return match (true) {
            $approver->status === ContractApprover::STATUS_APPROVED => 'approved',
            $approver->status === ContractApprover::STATUS_REJECTED => 'rejected',
            $this->isCurrentApprover($approver) => 'current',
            default => 'queued',
        };
    }
}
