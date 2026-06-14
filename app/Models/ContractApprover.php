<?php

namespace App\Models;

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
        'acted_at',
        'due_at',
        'reminder_sent_at',
    ];

    protected $casts = [
        'order' => 'integer',
        'acted_at' => 'datetime',
        'due_at' => 'datetime',
        'reminder_sent_at' => 'datetime',
    ];

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_RETURNED = 'returned';

    public const STATUS_SKIPPED = 'skipped';

    public static function getStatuses(): array
    {
        return [
            self::STATUS_PENDING => __('app.contract_approver.status.pending'),
            self::STATUS_APPROVED => __('app.contract_approver.status.approved'),
            self::STATUS_REJECTED => __('app.contract_approver.status.rejected'),
            self::STATUS_RETURNED => __('app.contract_approver.status.returned'),
            self::STATUS_SKIPPED => __('app.contract_approver.status.skipped'),
        ];
    }

    public static function getStatusColors(): array
    {
        return [
            self::STATUS_PENDING => 'warning',
            self::STATUS_APPROVED => 'success',
            self::STATUS_REJECTED => 'danger',
            self::STATUS_RETURNED => 'info',
            self::STATUS_SKIPPED => 'gray',
        ];
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

    /**
     * Start the review clock for this step — called when the approver
     * becomes the current one in the chain. Sets the SLA deadline and
     * clears any prior reminder flag.
     */
    public function startReview(int $slaDays): void
    {
        $this->update([
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

    public function hoursUntilDue(): ?int
    {
        if ($this->due_at === null) {
            return null;
        }

        return (int) round(now()->diffInHours($this->due_at, false));
    }

    /**
     * Whether a reminder should be sent now: once when the deadline is
     * within 12 hours, then again at most daily once overdue.
     */
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
