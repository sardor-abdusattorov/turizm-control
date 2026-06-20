<?php

namespace App\Models;

use App\Enums\ContractApproverStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractApprover extends Model
{
    use HasFactory;

    protected $fillable = [
        'contract_id',
        'user_id',
        'order',
        'status',
        'comment',
        'system_comment',
        'original_status',
        'acted_at',
        'due_at',
        'reminder_sent_at',
    ];

    protected $casts = [
        'order' => 'integer',
        'status' => ContractApproverStatus::class,
        'original_status' => ContractApproverStatus::class,
        'acted_at' => 'datetime',
        'due_at' => 'datetime',
        'reminder_sent_at' => 'datetime',
    ];

    public const STATUS_QUEUED = ContractApproverStatus::Queued;

    public const STATUS_PENDING = ContractApproverStatus::Pending;

    public const STATUS_APPROVED = ContractApproverStatus::Approved;

    public const STATUS_REJECTED = ContractApproverStatus::Rejected;

    public const STATUS_RETURNED = ContractApproverStatus::Returned;

    public const STATUS_SKIPPED = ContractApproverStatus::Skipped;

    public const STATUS_INVALIDATED = ContractApproverStatus::Invalidated;

    public function scopeActive($query)
    {
        return $query->whereNotIn('status', [self::STATUS_INVALIDATED, self::STATUS_SKIPPED]);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function markApproved(?string $comment = null): void
    {
        $this->update([
            'status' => self::STATUS_APPROVED,
            'comment' => $comment,
            'acted_at' => now(),
        ]);
    }

    public function markRejected(?string $comment = null): void
    {
        $this->update([
            'status' => self::STATUS_REJECTED,
            'comment' => $comment,
            'acted_at' => now(),
        ]);
    }

    public function markReturned(?string $comment = null): void
    {
        $this->update([
            'status' => self::STATUS_RETURNED,
            'comment' => $comment,
            'acted_at' => now(),
        ]);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function displayStatus(): ContractApproverStatus
    {
        return $this->status === self::STATUS_INVALIDATED && $this->original_status
            ? $this->original_status
            : $this->status;
    }

    public function wasCancelledAfterVerdict(): bool
    {
        return $this->status === self::STATUS_INVALIDATED && $this->original_status !== null;
    }

    public function startReview(int $slaDays): void
    {
        $this->update([
            'status' => self::STATUS_PENDING,
            'due_at' => now()->addDays($slaDays),
            'reminder_sent_at' => null,
        ]);
    }

    public function isOverdue(): bool
    {
        return $this->status === self::STATUS_PENDING
            && $this->due_at !== null
            && now()->greaterThan($this->due_at);
    }

    public function needsReminder(): bool
    {
        if ($this->status !== self::STATUS_PENDING || $this->due_at === null) {
            return false;
        }

        $now = now();

        if ($now->greaterThanOrEqualTo($this->due_at)) {
            return $this->reminder_sent_at === null
                || $this->reminder_sent_at->lessThan($now->copy()->subDay());
        }

        if ($now->greaterThanOrEqualTo($this->due_at->copy()->subHours(12))) {
            return $this->reminder_sent_at === null;
        }

        return false;
    }

    public function markReminded(): void
    {
        $this->update(['reminder_sent_at' => now()]);
    }
}
