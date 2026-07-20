<?php

namespace App\Models;

use App\Enums\ContractStatus;
use App\Enums\PaymentStatus;
use App\Models\Concerns\HasDocumentKey;
use App\Observers\ContractObserver;
use App\Services\Contracts\ApprovalChain;
use App\Services\Contracts\ContractFiles;
use App\Services\Contracts\ContractWorkflow;
use App\Services\Documents\ContractPlaceholderValues;
use App\Services\Documents\TemplateFiller;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

#[ObservedBy(ContractObserver::class)]
class Contract extends Model
{
    use HasDocumentKey, HasFactory;

    protected $fillable = [
        'number',
        'contract_template_id',
        'contract_type_id',
        'contact_id',
        'sponsor_id',
        'project_id',
        'currency_id',
        'responsible_id',
        'title',
        'amount',
        'status',
        'payment_status',
        'paid_percent',
        'signed_at',
        'document_file',
        'document_key',
        'pdf_file',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_percent' => 'decimal:2',
        'signed_at' => 'date',
        'status' => ContractStatus::class,
        'payment_status' => PaymentStatus::class,
    ];

    public const STATUS_DRAFT = ContractStatus::Draft;

    public const STATUS_IN_REVIEW = ContractStatus::InReview;

    public const STATUS_PENDING_DIRECTOR = ContractStatus::PendingDirector;

    public const STATUS_IN_REVIEW_DIRECTOR = ContractStatus::InReviewDirector;

    public const STATUS_APPROVED = ContractStatus::Approved;

    public const STATUS_REJECTED = ContractStatus::Rejected;

    /** @return array<string, string> value => label */
    public static function getStatuses(): array
    {
        return ContractStatus::options();
    }

    /**
     * Business fields whose change mid-flow must invalidate prior approvals.
     * Bookkeeping columns (status, document_key, pdf_file, signed_at, …) are
     * intentionally NOT in this list — they change as part of the workflow
     * itself and must never trigger a cancel-on-edit cascade.
     *
     * @var array<int, string>
     */
    public const REAPPROVAL_TRIGGER_FIELDS = [
        'number',
        'title',
        'amount',
        'currency_id',
        'contact_id',
        'sponsor_id',
        'contract_type_id',
        'contract_template_id',
        'document_file',
    ];

    private bool $preserveApprovedStatus = false;

    public function preserveApprovedOnNextSave(): self
    {
        $this->preserveApprovedStatus = true;

        return $this;
    }

    public function maybeInvalidateOnEdit(): void
    {
        if ($this->preserveApprovedStatus) {
            return;
        }

        if (! in_array($this->getOriginal('status'), [
            self::STATUS_IN_REVIEW,
            self::STATUS_PENDING_DIRECTOR,
            self::STATUS_IN_REVIEW_DIRECTOR,
            self::STATUS_APPROVED,
            self::STATUS_REJECTED,
        ], true)) {
            return;
        }

        $touched = array_keys($this->getDirty());
        $businessTouched = array_intersect($touched, self::REAPPROVAL_TRIGGER_FIELDS);

        if (empty($businessTouched)) {
            return;
        }

        $this->status = self::STATUS_DRAFT;
        $this->signed_at = null;

        $previousUserIds = $this->activeApprovers()
            ->orderBy('order')
            ->pluck('user_id')
            ->all();

        $this->invalidateAllApprovers('invalidated_on_edit');

        if ($previousUserIds === []) {
            $this->buildApprovalChainFromFlow();

            return;
        }

        $this->approvalChain()->requeue($this, $previousUserIds);
    }

    /**
     * Bounce the contract back to Draft after its document was edited, unless
     * the *only* person who edited was the current approver tweaking the doc
     * before their verdict (we keep their Approve / Reject buttons alive).
     * Any other editor — the author, a different approver, or the approver
     * co-editing alongside someone else — means approvals are now stale.
     *
     * Runs from the OnlyOffice save-callback on both the session-end save
     * (status 2) and a mid-session forcesave (status 6): either way the bytes
     * on disk have already changed, so the chain must not stay live. It is
     * idempotent — once the contract is a Draft, repeated saves are no-ops.
     *
     * @param  list<int>  $editorIds  user ids OnlyOffice reported as editors
     */
    public function reinvalidateAfterDocumentEdit(array $editorIds = []): void
    {
        $this->refresh();

        if ($this->status === self::STATUS_DRAFT) {
            return;
        }

        $current = $this->currentApprover();

        if ($current && $editorIds === [$current->user_id]) {
            return;
        }

        $previousUserIds = $this->activeApprovers()
            ->orderBy('order')
            ->pluck('user_id')
            ->all();

        $this->forceFill([
            'status' => self::STATUS_DRAFT,
            'signed_at' => null,
        ])->saveQuietly();

        $this->invalidateAllApprovers('invalidated_on_document_save');

        $this->approvalChain()->requeue($this, $previousUserIds);
    }

    public function files(): ContractFiles
    {
        return app(ContractFiles::class);
    }

    public function documentPath(): string
    {
        return $this->files()->documentPath($this);
    }

    public function documentAbsolutePath(): string
    {
        return $this->files()->documentAbsolutePath($this);
    }

    public function documentExists(): bool
    {
        return $this->files()->documentExists($this);
    }

    public function buildDocumentFromTemplate(TemplateFiller $filler, ContractPlaceholderValues $values): void
    {
        $this->files()->buildFromTemplate($this, $filler, $values);
    }

    public function approvalChain(): ApprovalChain
    {
        return app(ApprovalChain::class);
    }

    public function buildApprovalChainFromFlow(): int
    {
        return $this->approvalChain()->buildFromFlow($this);
    }

    /**
     * Preview of the approval chain the global flow would produce, as
     * "Department — User" lines, for display on the create form.
     *
     * @return array<int, string>
     */
    public static function approvalChainPreview(): array
    {
        return app(ApprovalChain::class)->preview();
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ContractTemplate::class, 'contract_template_id');
    }

    public function contractType(): BelongsTo
    {
        return $this->belongsTo(ContractType::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * The sponsor this contract is signed with, on sponsorship («Спонсорство»)
     * contracts. Mutually exclusive with {@see contact()} — the type's
     * counterparty_kind decides which one is filled.
     */
    public function sponsor(): BelongsTo
    {
        return $this->belongsTo(Sponsor::class);
    }

    /**
     * The counterparty this contract faces, whichever kind it is: the sponsor
     * on sponsorship contracts, the contact otherwise.
     */
    public function counterparty(): Contact|Sponsor|null
    {
        return $this->sponsor_id !== null ? $this->sponsor : $this->contact;
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ContractAttachment::class)->orderBy('sort')->orderBy('id');
    }

    public function approvers(): HasMany
    {
        return $this->hasMany(ContractApprover::class)->orderBy('order');
    }

    public function activeApprovers(): HasMany
    {
        return $this->hasMany(ContractApprover::class)
            ->whereNotIn('status', [ContractApprover::STATUS_INVALIDATED, ContractApprover::STATUS_SKIPPED])
            ->orderBy('order');
    }

    public function currentApprover(): ?ContractApprover
    {
        // Reuse the eager-loaded relation when present — table columns call
        // this per row and must not issue a query each (N+1).
        if ($this->relationLoaded('activeApprovers')) {
            return $this->activeApprovers
                ->where('status', ContractApprover::STATUS_PENDING)
                ->sortBy('order')
                ->first();
        }

        return $this->activeApprovers()
            ->where('status', ContractApprover::STATUS_PENDING)
            ->orderBy('order')
            ->first();
    }

    public function nextInLineApprover(): ?ContractApprover
    {
        return $this->activeApprovers()
            ->whereIn('status', [ContractApprover::STATUS_PENDING, ContractApprover::STATUS_QUEUED])
            ->orderBy('order')
            ->first();
    }

    public function invalidateAllApprovers(?string $note = null): int
    {
        $decided = [
            ContractApprover::STATUS_APPROVED,
            ContractApprover::STATUS_REJECTED,
        ];
        $invalidated = ContractApprover::STATUS_INVALIDATED;

        // Rows that already had a verdict need their `original_status`
        // preserved (the audit trail still shows the original outcome).
        // Iterate them per-row — they're typically 0-2 per contract and
        // need to round-trip acted_at through the Eloquent date cast.
        $verdictCount = 0;
        foreach ($this->approvers()->whereIn('status', $decided)->get() as $approver) {
            $approver->update([
                'original_status' => $approver->status,
                'status' => $invalidated,
                'system_comment' => $note,
                'acted_at' => $approver->acted_at,
            ]);
            $verdictCount++;
        }

        // Everything else can flip in a single batch UPDATE — no per-row
        // state to preserve.
        $pending = $this->approvers()
            ->whereNotIn('status', array_merge($decided, [$invalidated]))
            ->update([
                'status' => $invalidated,
                'system_comment' => $note,
                'acted_at' => now(),
            ]);

        return $verdictCount + $pending;
    }

    public function isCurrentApprover(User $user): bool
    {
        $current = $this->currentApprover();

        return $current !== null && $current->user_id === $user->id;
    }

    public function hasApprovers(): bool
    {
        return $this->approvers()->exists();
    }

    public function canRebuildChainOnSubmit(): bool
    {
        return $this->activeApprovers()->doesntExist()
            && ! empty(self::approvalChainPreview());
    }

    public function allApproved(): bool
    {
        return $this->activeApprovers()->exists()
            && $this->activeApprovers()->where('status', '!=', ContractApprover::STATUS_APPROVED)->doesntExist();
    }

    public function directorUser(): ?User
    {
        return User::query()
            ->whereHas('roles', fn (Builder $query) => $query->where('name', self::DIRECTOR_ROLE))
            ->where('status', User::STATUS_ACTIVE)
            ->first();
    }

    public function hasDirectorApprover(): bool
    {
        $director = $this->directorUser();

        return $director !== null
            && $this->activeApprovers()->where('user_id', $director->id)->exists();
    }

    public function isInDirectorStage(): bool
    {
        $director = $this->directorUser();

        return $director !== null
            && $this->activeApprovers()
                ->where('user_id', $director->id)
                ->whereIn('status', [ContractApprover::STATUS_PENDING, ContractApprover::STATUS_QUEUED])
                ->exists();
    }

    public function appendDirectorApprover(): ?ContractApprover
    {
        $director = $this->directorUser();

        if (! $director || $this->hasDirectorApprover()) {
            return null;
        }

        return ContractApprover::create([
            'contract_id' => $this->id,
            'user_id' => $director->id,
            'order' => ((int) $this->activeApprovers()->max('order')) + 1,
            'status' => ContractApprover::STATUS_QUEUED,
        ]);
    }

    public function canBeSubmittedBy(?User $user = null): bool
    {
        $user ??= auth()->user();

        return ContractWorkflow::approvalEnabled()
            && $user
            && $this->status === self::STATUS_DRAFT
            && ($this->responsible_id === $user->id || $user->hasRole('super_admin'))
            && $this->documentExists()
            && ($this->activeApprovers()->exists() || $this->canRebuildChainOnSubmit());
    }

    public function canBeApprovedBy(?User $user = null): bool
    {
        $user ??= auth()->user();

        return $user
            && $user->can('approve_contracts')
            && in_array($this->status, [self::STATUS_IN_REVIEW, self::STATUS_IN_REVIEW_DIRECTOR], true)
            && $this->isCurrentApprover($user);
    }

    public function canBeSentToDirectorBy(?User $user = null): bool
    {
        $user ??= auth()->user();

        return $user
            && $this->status === self::STATUS_PENDING_DIRECTOR
            && ($this->responsible_id === $user->id || $user->hasRole('super_admin'))
            && $this->directorUser() !== null;
    }

    public function canBeViewedBy(?User $user = null): bool
    {
        $user ??= auth()->user();

        if (! $user) {
            return false;
        }

        // Super admin is the only universal exception — drafts included.
        if ($user->hasRole('super_admin')) {
            return true;
        }

        // Your own contract is visible in any status, drafts included.
        if ($this->responsible_id === $user->id) {
            return true;
        }

        // Someone else's draft is private to its author until it is submitted.
        if ($this->status === self::STATUS_DRAFT) {
            return false;
        }

        // Past the draft stage: oversight and view_all_contracts see the whole
        // pipeline; everyone else must sit in the approval chain.
        if ($user->hasAnyRole(self::OVERSIGHT_ROLES) || $user->can('view_all_contracts')) {
            return true;
        }

        return $this->approvers()->where('user_id', $user->id)->exists();
    }

    public function canBeEditedBy(?User $user = null): bool
    {
        $user ??= auth()->user();

        if (! $user) {
            return false;
        }

        if ($user->hasRole('super_admin')) {
            return true;
        }

        // A filed (approved) contract only reopens for holders of the
        // dedicated permission — granted per-role in Роли, so the admin
        // decides who may fix archive entries; authorship alone is not
        // enough. Saving then either keeps it filed via the «already signed»
        // switch or honestly sends it back through approval; the signed
        // OnlyOffice document itself stays read-only either way.
        if ($this->status === self::STATUS_APPROVED) {
            return $user->can('update_approved_contract');
        }

        // The responsible manager owns the document while it is a draft, in
        // review, or has come back rejected.
        if ($this->responsible_id === $user->id
            && in_array($this->status, [
                self::STATUS_DRAFT,
                self::STATUS_IN_REVIEW,
                self::STATUS_REJECTED,
            ], true)) {
            return true;
        }

        // The approver whose turn it is may tweak the document before
        // approving it.
        return $this->currentApprover()?->user_id === $user->id;
    }

    /**
     * Whether the user may edit the DOCUMENT (OnlyOffice), not just the form:
     * an approved contract can be reopened for field fixes, but its signed
     * document stays read-only — document changes go through re-approval.
     */
    public function documentEditableBy(?User $user = null): bool
    {
        return $this->status !== self::STATUS_APPROVED && $this->canBeEditedBy($user);
    }

    public function canBeDeletedBy(?User $user = null): bool
    {
        $user ??= auth()->user();

        return $user
            && $this->status === self::STATUS_DRAFT
            && ($this->responsible_id === $user->id || $user->hasRole('super_admin'));
    }

    /**
     * Whether editing the document right now would invalidate the approvals and
     * bounce the contract back to draft — true once it is anywhere in the
     * approval flow. Used to warn the editor before they open the document.
     */
    public function documentEditWouldResetApprovals(): bool
    {
        return in_array($this->status, [
            self::STATUS_IN_REVIEW,
            self::STATUS_PENDING_DIRECTOR,
            self::STATUS_IN_REVIEW_DIRECTOR,
        ], true);
    }

    /**
     * Whoever may edit contract data may also curate its dossier. Unlike
     * editing the contract's terms, this stays open AFTER full approval — the
     * signed scan, SWIFT slip and act arrive once the contract is already
     * approved. While the contract is mid-approval the dossier freezes:
     * approvers must review a fixed set of files, not a moving target.
     */
    public function attachmentsManageableBy(?User $user = null): bool
    {
        $user ??= auth()->user();

        return $user !== null
            && ! $this->documentEditWouldResetApprovals()
            && ($user->hasRole('super_admin') || $user->can('update_contract'));
    }

    public function scopeAwaitingApprovalBy(Builder $query, ?User $user = null): Builder
    {
        $user ??= auth()->user();

        if (! $user) {
            return $query->whereRaw('0 = 1');
        }

        return $query
            // Both review stages: a director reviewing (IN_REVIEW_DIRECTOR)
            // must see their queue too, not only the regular chain stage.
            ->whereIn('status', [self::STATUS_IN_REVIEW, self::STATUS_IN_REVIEW_DIRECTOR])
            ->whereIn('id', function ($sub) use ($user): void {
                $sub->select('a1.contract_id')
                    ->from('contract_approvers as a1')
                    ->where('a1.user_id', $user->id)
                    ->where('a1.status', ContractApprover::STATUS_PENDING)
                    ->whereNotExists(function ($inner): void {
                        $inner->select(DB::raw(1))
                            ->from('contract_approvers as a2')
                            ->whereColumn('a2.contract_id', 'a1.contract_id')
                            ->where('a2.status', ContractApprover::STATUS_PENDING)
                            ->whereColumn('a2.order', '<', 'a1.order');
                    });
            });
    }

    public function scopeInvolvingApprover(Builder $query, ?User $user = null): Builder
    {
        $user ??= auth()->user();

        if (! $user) {
            return $query->whereRaw('0 = 1');
        }

        return $query->whereHas(
            'approvers',
            fn (Builder $q) => $q->where('user_id', $user->id),
        );
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderByDesc('paid_at');
    }

    public function paidPercent(): float
    {
        if (array_key_exists('paid_percent', $this->attributes)) {
            return (float) $this->paid_percent;
        }

        return (float) $this->payments()->sum('percent');
    }

    /**
     * Money actually collected on this contract. Contracts track a paid
     * *percent* (maintained by the payment observers), not an absolute figure,
     * so the collected amount is derived from the total — kept as a helper so
     * every project/contact/sponsor income breakdown reads it the same way.
     */
    public function paidAmount(): float
    {
        return round((float) $this->amount * $this->paidPercent() / 100, 2);
    }

    public function remainingPercent(): float
    {
        return max(0.0, round(100 - $this->paidPercent(), 2));
    }

    public function isFullyPaid(): bool
    {
        return $this->payment_status === PaymentStatus::FullyPaid;
    }

    public function canAcceptPayment(): bool
    {
        return $this->status === self::STATUS_APPROVED && ! $this->isFullyPaid();
    }

    /**
     * Contracts that can still take a payment: approved and not yet fully paid.
     * The query-level counterpart of canAcceptPayment().
     *
     * @param  Builder<Contract>  $query
     * @return Builder<Contract>
     */
    public function scopeAcceptingPayments(Builder $query): Builder
    {
        return $query
            ->where('status', self::STATUS_APPROVED)
            ->where('payment_status', '!=', PaymentStatus::FullyPaid->value);
    }

    public const DIRECTOR_ROLE = 'director';

    public const OVERSIGHT_ROLES = ['super_admin', self::DIRECTOR_ROLE];

    public function scopeVisibleTo(Builder $query, ?User $user = null): Builder
    {
        $user ??= auth()->user();

        if (! $user) {
            return $query->whereRaw('0 = 1');
        }

        // Super admin is the only universal exception — they see every
        // contract, everyone's drafts included.
        if ($user->hasRole('super_admin')) {
            return $query;
        }

        // A draft is private to its author until it is submitted for approval.
        // Everyone else sees their own contracts (any status) plus the
        // non-draft contracts they're entitled to: the whole live pipeline for
        // oversight / view_all_contracts, otherwise just the ones they approve.
        $seesWholePipeline = $user->hasAnyRole(self::OVERSIGHT_ROLES)
            || $user->can('view_all_contracts');

        return $query->where(function (Builder $scoped) use ($user, $seesWholePipeline): void {
            // Columns are table-qualified so the scope is safe to use on a
            // query that joins another table carrying a `status` column (e.g.
            // the financial summary joins `currencies`).
            $scoped->where('contracts.responsible_id', $user->id);

            $scoped->orWhere(function (Builder $pipeline) use ($user, $seesWholePipeline): void {
                $pipeline->where('contracts.status', '!=', self::STATUS_DRAFT);

                if (! $seesWholePipeline) {
                    $pipeline->whereHas('approvers', fn (Builder $q) => $q->where('user_id', $user->id));
                }
            });
        });
    }
}
