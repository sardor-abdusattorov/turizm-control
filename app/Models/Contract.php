<?php

namespace App\Models;

use App\Enums\ContractStatus;
use App\Enums\PaymentStatus;
use App\Observers\ContractObserver;
use App\Services\Contracts\ApprovalChain;
use App\Services\Contracts\ContractFiles;
use App\Services\Contracts\ContractWorkflow;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

#[ObservedBy(ContractObserver::class)]
class Contract extends Model
{
    use HasFactory;

    protected $fillable = [
        'number',
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

    /** @var array<int, string> */
    public const REAPPROVAL_TRIGGER_FIELDS = [
        'number',
        'title',
        'amount',
        'currency_id',
        'contact_id',
        'sponsor_id',
        'contract_type_id',
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

    public function files(): ContractFiles
    {
        return app(ContractFiles::class);
    }

    public function approvalChain(): ApprovalChain
    {
        return app(ApprovalChain::class);
    }

    public function buildApprovalChainFromFlow(): int
    {
        return $this->approvalChain()->buildFromFlow($this);
    }

    /** @return array<int, string> */
    public static function approvalChainPreview(): array
    {
        return app(ApprovalChain::class)->preview();
    }

    public function contractType(): BelongsTo
    {
        return $this->belongsTo(ContractType::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function sponsor(): BelongsTo
    {
        return $this->belongsTo(Sponsor::class);
    }

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

    /** @return Attribute<list<string>, never> */
    protected function attachmentFiles(): Attribute
    {
        return Attribute::get(fn (): array => $this->attachments()->pluck('file_path')->all());
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
            && $this->attachments()->exists()
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

        if ($user->hasRole('super_admin')) {
            return true;
        }

        if ($this->responsible_id === $user->id) {
            return true;
        }

        if ($this->status === self::STATUS_DRAFT) {
            return false;
        }

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

        if ($this->status === self::STATUS_APPROVED) {
            return $user->can('update_approved_contract');
        }

        if ($this->responsible_id === $user->id
            && in_array($this->status, [
                self::STATUS_DRAFT,
                self::STATUS_IN_REVIEW,
                self::STATUS_REJECTED,
            ], true)) {
            return true;
        }

        return $this->currentApprover()?->user_id === $user->id;
    }

    public function canBeDeletedBy(?User $user = null): bool
    {
        $user ??= auth()->user();

        return $user
            && $this->status === self::STATUS_DRAFT
            && ($this->responsible_id === $user->id || $user->hasRole('super_admin'));
    }

    public function documentEditWouldResetApprovals(): bool
    {
        return in_array($this->status, [
            self::STATUS_IN_REVIEW,
            self::STATUS_PENDING_DIRECTOR,
            self::STATUS_IN_REVIEW_DIRECTOR,
        ], true);
    }

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

        if ($user->hasRole('super_admin')) {
            return $query;
        }

        $seesWholePipeline = $user->hasAnyRole(self::OVERSIGHT_ROLES)
            || $user->can('view_all_contracts');

        return $query->where(function (Builder $scoped) use ($user, $seesWholePipeline): void {
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
