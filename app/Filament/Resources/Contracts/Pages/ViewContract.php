<?php

namespace App\Filament\Resources\Contracts\Pages;

use App\Enums\PaymentStatus;
use App\Filament\Resources\Contracts\ContractResource;
use App\Filament\Support\ImageUpload;
use App\Models\Contract;
use App\Models\ContractApprover;
use App\Models\Payment;
use App\Services\Contracts\ContractWorkflow;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
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
        if ($this->record->status !== Contract::STATUS_IN_REVIEW) {
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
                        ->step('0.01')
                        ->suffix('%')
                        ->helperText(fn (): string => __('app.label.remaining_to_pay', [
                            'percent' => rtrim(rtrim(number_format($this->record->remainingPercent(), 2, '.', ''), '0'), '.'),
                        ])),

                    DatePicker::make('paid_at')
                        ->label(__('app.label.paid_at'))
                        ->required()
                        ->native(false)
                        ->maxDate(now())
                        ->default(now()),

                    ImageUpload::make(Payment::SCREENSHOT_DIR, 'screenshot')
                        ->label(__('app.label.screenshot'))
                        ->required(),
                ])
                ->action(function (array $data): void {
                    if (! $this->record->canAcceptPayment()) {
                        Notification::make()->title(__('app.message.action_not_allowed'))->danger()->send();

                        return;
                    }

                    $remaining = $this->record->remainingPercent();

                    if ((float) $data['percent'] > $remaining + 0.001) {
                        Notification::make()
                            ->title(__('app.message.payment_exceeds_remaining', [
                                'percent' => rtrim(rtrim(number_format($remaining, 2, '.', ''), '0'), '.'),
                            ]))
                            ->danger()
                            ->send();

                        return;
                    }

                    Payment::create([
                        'contract_id' => $this->record->id,
                        'percent' => $data['percent'],
                        'paid_at' => $data['paid_at'],
                        'screenshot' => $data['screenshot'],
                    ]);

                    Notification::make()->title(__('app.message.payment_recorded'))->success()->send();
                    $this->record->refresh();
                }),

            Action::make('downloadPdf')
                ->label(__('app.action.download_pdf'))
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->url(fn () => route('contracts.pdf.download', ['contract' => $this->record]))
                ->visible(fn (): bool => $this->record?->status === Contract::STATUS_APPROVED
                    && self::userCanExportContract()),

            EditAction::make()
                ->icon('heroicon-o-pencil-square')
                ->visible(fn () => $this->record?->canBeEditedBy()),
        ];
    }

    public static function userCanExportContract(): bool
    {
        $user = auth()->user();

        return $user !== null
            && ($user->hasRole('super_admin') || $user->can('export_contract'));
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

        $bytes = Storage::disk('local')->size($this->record->documentPath());

        return number_format($bytes / 1024, 1).' KB';
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

    public function paymentScreenshotUrl(Payment $payment): ?string
    {
        return $payment->screenshotUrl();
    }

    public function approverAvatar(ContractApprover $approver): string
    {
        return $approver->user?->getFilamentAvatarUrl()
            ?? 'https://ui-avatars.com/api/?name='.urlencode($approver->user?->name ?? '?').'&background=E0E7FF&color=4338CA&size=80';
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

        if ($approver->status === ContractApprover::STATUS_RETURNED) {
            return ['icon' => 'heroicon-s-arrow-uturn-left', 'color' => 'info'];
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
            $approver->status === ContractApprover::STATUS_RETURNED => 'returned',
            $this->isCurrentApprover($approver) => 'current',
            default => 'queued',
        };
    }

    /**
     * @return array{icon: string, color: string}
     */
    public function activityVisual(string $event): array
    {
        return match ($event) {
            'Contract Submitted' => ['icon' => 'heroicon-o-paper-airplane', 'color' => 'info'],
            'Contract Sent To Director' => ['icon' => 'heroicon-o-arrow-up-circle', 'color' => 'primary'],
            'Contract Step Approved', 'Contract Approved' => ['icon' => 'heroicon-o-check-circle', 'color' => 'success'],
            'Contract Rejected' => ['icon' => 'heroicon-o-x-circle', 'color' => 'danger'],
            'Contract Returned' => ['icon' => 'heroicon-o-arrow-uturn-left', 'color' => 'warning'],
            'Contract Document Saved', 'Contract Document Forcesave' => ['icon' => 'heroicon-o-document-text', 'color' => 'info'],
            'Contract Edit Invalidated' => ['icon' => 'heroicon-o-no-symbol', 'color' => 'warning'],
            'created' => ['icon' => 'heroicon-o-sparkles', 'color' => 'info'],
            'updated' => ['icon' => 'heroicon-o-pencil-square', 'color' => 'gray'],
            'deleted' => ['icon' => 'heroicon-o-trash', 'color' => 'danger'],
            default => ['icon' => 'heroicon-o-information-circle', 'color' => 'gray'],
        };
    }

    public function activityGroup(string $event): string
    {
        return match ($event) {
            'Contract Submitted', 'Contract Sent To Director', 'Contract Step Approved',
            'Contract Approved', 'Contract Rejected', 'Contract Returned' => 'workflow',
            default => 'edit',
        };
    }
}
