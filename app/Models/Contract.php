<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class Contract extends Model
{
    use HasTranslations;

    public $translatable = ['title', 'description', 'data'];

    protected $fillable = [
        'number',
        'order_type_id',
        'contact_id',
        'currency_id',
        'responsible_id',
        'title',
        'description',
        'amount',
        'status',
        'deadline_at',
        'signed_at',
        'data',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'deadline_at' => 'date',
        'signed_at' => 'date',
    ];

    public const STATUS_DRAFT = 'draft';

    public const STATUS_IN_REVIEW = 'in_review';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_ARCHIVED = 'archived';

    public static function getStatuses(): array
    {
        return [
            self::STATUS_DRAFT => __('app.contract.status.draft'),
            self::STATUS_IN_REVIEW => __('app.contract.status.in_review'),
            self::STATUS_APPROVED => __('app.contract.status.approved'),
            self::STATUS_REJECTED => __('app.contract.status.rejected'),
            self::STATUS_ARCHIVED => __('app.contract.status.archived'),
        ];
    }

    public static function getStatusColors(): array
    {
        return [
            self::STATUS_DRAFT => 'gray',
            self::STATUS_IN_REVIEW => 'warning',
            self::STATUS_APPROVED => 'success',
            self::STATUS_REJECTED => 'danger',
            self::STATUS_ARCHIVED => 'gray',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $contract): void {
            if (! $contract->number) {
                $contract->number = static::generateNumber();
            }
        });
    }

    public static function generateNumber(): string
    {
        $year = now()->year;
        $prefix = 'КОНТ';

        $lastSeq = static::query()
            ->where('number', 'like', "{$prefix}-{$year}-%")
            ->orderByDesc('id')
            ->value('number');

        $next = $lastSeq
            ? ((int) substr($lastSeq, strrpos($lastSeq, '-') + 1)) + 1
            : 1;

        return sprintf('%s-%d-%03d', $prefix, $year, $next);
    }

    public function orderType(): BelongsTo
    {
        return $this->belongsTo(OrderType::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }

    public function approvers(): HasMany
    {
        return $this->hasMany(ContractApprover::class)->orderBy('order');
    }

    /**
     * Get the approver row that is currently waiting on a decision
     * (the first one with status = pending in chain order).
     */
    public function currentApprover(): ?ContractApprover
    {
        return $this->approvers()
            ->where('status', ContractApprover::STATUS_PENDING)
            ->orderBy('order')
            ->first();
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

    public function allApproved(): bool
    {
        return $this->hasApprovers()
            && $this->approvers()->where('status', '!=', ContractApprover::STATUS_APPROVED)->doesntExist();
    }

    public function wasRejected(): bool
    {
        return $this->approvers()->where('status', ContractApprover::STATUS_REJECTED)->exists();
    }

    /**
     * Reset all approver decisions back to pending and revert contract
     * to draft. Called when a manager edits a contract that was already
     * sent for review.
     */
    public function resetApprovalState(): void
    {
        $this->approvers()->update([
            'status' => ContractApprover::STATUS_PENDING,
            'comment' => null,
            'acted_at' => null,
        ]);

        $this->update(['status' => self::STATUS_DRAFT]);
    }

    public function canBeSubmittedBy(?User $user = null): bool
    {
        $user ??= auth()->user();

        return $user
            && $this->status === self::STATUS_DRAFT
            && ($this->responsible_id === $user->id || $user->hasRole('super_admin'))
            && $this->hasApprovers();
    }

    public function canBeApprovedBy(?User $user = null): bool
    {
        $user ??= auth()->user();

        return $user
            && $this->status === self::STATUS_IN_REVIEW
            && $this->isCurrentApprover($user);
    }

    public function canBeEditedBy(?User $user = null): bool
    {
        $user ??= auth()->user();

        if (! $user) {
            return false;
        }

        if ($user->hasRole('super_admin')) {
            return $this->status !== self::STATUS_ARCHIVED;
        }

        return $this->responsible_id === $user->id
            && in_array($this->status, [
                self::STATUS_DRAFT,
                self::STATUS_IN_REVIEW,
                self::STATUS_REJECTED,
            ], true);
    }

    public function canBeDeletedBy(?User $user = null): bool
    {
        $user ??= auth()->user();

        return $user
            && $this->status === self::STATUS_DRAFT
            && ($this->responsible_id === $user->id || $user->hasRole('super_admin'));
    }

    public function canBeArchivedBy(?User $user = null): bool
    {
        $user ??= auth()->user();

        return $user
            && $this->status !== self::STATUS_ARCHIVED
            && $user->hasRole('super_admin');
    }

    /**
     * Build the suggested approval chain for a manager: take their
     * defaultRecipients, group by department code, and emit them in the
     * order configured in settings.approval.flow. Used as the default
     * value for the create wizard's approver repeater.
     *
     * @return array<int, array{user_id: int}>
     */
    public static function suggestApproverChain(?User $user = null): array
    {
        $user ??= auth()->user();

        if (! $user) {
            return [];
        }

        $flow = Department::approvalFlow();

        $recipientIds = $user->defaultRecipients()
            ->where('users.status', User::STATUS_ACTIVE)
            ->pluck('users.id');

        $recipients = User::query()
            ->whereIn('id', $recipientIds)
            ->whereHas('department', fn ($q) => $q->approvers())
            ->with('department')
            ->get()
            ->groupBy(fn (User $u) => $u->department?->code);

        $chain = [];

        foreach ($flow as $code) {
            foreach ($recipients->get($code, collect()) as $recipient) {
                $chain[] = ['user_id' => $recipient->id];
            }
        }

        return $chain;
    }
}
