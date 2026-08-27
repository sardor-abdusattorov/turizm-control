<?php

namespace App\Filament\Resources\Contracts\Pages;

use App\Enums\PaymentStatus;
use App\Filament\Resources\Contracts\ContractResource;
use App\Filament\Support\ExportPermission;
use App\Filament\Support\PaymentFilesUpload;
use App\Models\Contact;
use App\Models\Contract;
use App\Models\ContractApprover;
use App\Models\ContractAttachment;
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
use Livewire\Attributes\On;
use Spatie\Activitylog\Models\Activity;

class ViewContract extends ViewRecord
{
    protected static string $resource = ContractResource::class;

    protected string $view = 'filament.resources.contracts.pages.view-contract';

    public function mount(int|string $record): void
    {
        parent::mount($record);

        // The progress band counts approvers and names the current one — load
        // them once instead of a query per row (N+1). The chain and payment
        // tables are widgets and load their own relations.
        $this->record->loadMissing([
            'approvers.user',
            'activeApprovers.user',
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

    /**
     * The Attachments tab badge counts the dossier, so a file filed in the
     * panel below has to invalidate the count this page cached.
     */
    #[On('attachments-saved')]
    public function refreshAttachmentCount(): void
    {
        $this->cachedAttachments = null;
    }

    public function canManageAttachments(): bool
    {
        return $this->record->attachmentsManageableBy();
    }

    /**
     * The counterparty's dossier as a native Filament modal, opened from the
     * contact row in the sidebar. Fields are grouped exactly the way
     * ContactForm splits them: identity, tax/legal, contacts, bank details.
     */
    public function contactDetailsAction(): Action
    {
        return Action::make('contactDetails')
            ->modalHeading(fn (): string => $this->record->contact?->name ?? '')
            ->modalDescription(fn (): ?string => $this->record->contact?->legal_form)
            ->modalIcon('heroicon-o-building-office-2')
            ->modalWidth('2xl')
            ->modalContent(fn () => view(
                'filament.resources.contracts.pages.view-contract.contact-modal',
                ['groups' => $this->contactGroups()],
            ))
            ->modalSubmitAction(false)
            ->modalCancelAction(false);
    }

    /**
     * @return list<array{0: string, 1: list<array{0: string, 1: string, 2: string}>}>
     */
    public function contactGroups(): array
    {
        $contact = $this->record->contact;

        if (! $contact) {
            return [];
        }

        // The account matching this contract's currency (falls back to a generic one).
        $bankAccount = $contact->bankAccountFor($this->record->currency_id);
        $contactType = $contact->type === Contact::TYPE_INDIVIDUAL
            ? __('app.contact.type.individual')
            : __('app.contact.type.legal');

        $groups = [
            [__('app.label.basic_information'), [
                ['heroicon-o-building-office-2', __('app.label.name'), $contact->name],
                ['heroicon-o-identification', __('app.label.contact_type'), $contactType],
                ['heroicon-o-tag', __('app.label.legal_form'), $contact->legal_form],
                ['heroicon-o-map-pin', __('app.label.address'), $contact->address],
            ]],

            [__('app.label.legal_details'), [
                ['heroicon-o-finger-print', __('app.label.inn'), $contact->inn],
                ['heroicon-o-finger-print', __('app.label.pinfl'), $contact->pinfl],
                ['heroicon-o-bookmark', __('app.label.oked'), $contact->oked],
                ['heroicon-o-user', __('app.label.director_name'), $contact->director_name],
                ['heroicon-o-user-circle', __('app.label.contact_person'), $contact->contact_person],
            ]],

            [__('app.label.contacts'), [
                ['heroicon-o-phone', __('app.label.phone'), $contact->phone],
                ['heroicon-o-envelope', __('app.label.email'), $contact->email],
            ]],

            [__('app.label.bank_requisites'), [
                ['heroicon-o-building-library', __('app.label.bank_name'), $bankAccount?->bank_name],
                ['heroicon-o-map-pin', __('app.label.bank_address'), $bankAccount?->bank_address],
                ['heroicon-o-banknotes', __('app.label.bank_account'), $bankAccount?->account_number],
                ['heroicon-o-hashtag', __('app.label.mfo'), $bankAccount?->mfo],
                ['heroicon-o-globe-alt', __('app.label.swift'), $bankAccount?->swift],
            ]],
        ];

        // Drop blank rows, then any group left empty by that filter.
        $groups = array_map(
            fn (array $group): array => [$group[0], array_values(array_filter(
                $group[1],
                fn (array $row): bool => ! empty($row[2]),
            ))],
            $groups,
        );

        return array_values(array_filter($groups, fn (array $group): bool => $group[1] !== []));
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

    /**
     * Payment progress summary for the View page — the band above the ledger.
     * The rows themselves come from ContractPaymentsTableWidget.
     *
     * @return array{
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
            'paid_percent' => $paid,
            'remaining_percent' => $this->record->remainingPercent(),
            'status' => $this->record->payment_status ?? PaymentStatus::NotPaid,
            'can_add' => $this->record->canAcceptPayment()
                && (bool) auth()->user()?->can('create_payment'),
        ];
    }

    public function approverAvatar(ContractApprover $approver): string
    {
        return $approver->user?->avatarUrl()
            ?? 'https://ui-avatars.com/api/?name=%3F&background=E0E7FF&color=4338CA&size=80';
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
}
