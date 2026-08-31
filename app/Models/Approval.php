<?php

namespace App\Models;

use App\Enums\ApprovalStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Approval extends Model
{
    use HasFactory;

    protected $fillable = [
        'approvable_type',
        'approvable_id',
        'user_id',
        'order',
        'round',
        'status',
        'original_status',
        'comment',
        'acted_at',
        'due_at',
        'reminder_sent_at',
    ];

    protected $casts = [
        'order' => 'integer',
        'round' => 'integer',
        'status' => ApprovalStatus::class,
        'original_status' => ApprovalStatus::class,
        'acted_at' => 'datetime',
        'due_at' => 'datetime',
        'reminder_sent_at' => 'datetime',
    ];

    public function approvable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', '!=', ApprovalStatus::Invalidated);
    }

    public function approve(?string $comment = null): void
    {
        $this->update([
            'status' => ApprovalStatus::Approved,
            'comment' => $comment,
            'acted_at' => now(),
        ]);
    }

    public function reject(?string $comment = null): void
    {
        $this->update([
            'status' => ApprovalStatus::Rejected,
            'comment' => $comment,
            'acted_at' => now(),
        ]);
    }

    public function invalidate(): void
    {
        if ($this->status === ApprovalStatus::Invalidated) {
            return;
        }

        $this->update([
            'original_status' => $this->status,
            'status' => ApprovalStatus::Invalidated,
        ]);
    }

    public function startReview(?int $slaDays = null): void
    {
        $this->update([
            'status' => ApprovalStatus::Pending,
            'due_at' => $slaDays ? now()->addDays($slaDays) : null,
            'reminder_sent_at' => null,
        ]);
    }

    /**
     * What the row should read as. A voided round keeps showing the verdict it
     * carried — that is the point of keeping it — but a row voided while its
     * owner was still deciding reads as voided, not as "under review".
     */
    public function displayStatus(): ApprovalStatus
    {
        return $this->status === ApprovalStatus::Invalidated && (bool) $this->original_status?->isFinal()
            ? $this->original_status
            : $this->status;
    }

    public function isVoided(): bool
    {
        return $this->status === ApprovalStatus::Invalidated;
    }

    public function isOverdue(): bool
    {
        return $this->status === ApprovalStatus::Pending
            && $this->due_at !== null
            && now()->greaterThan($this->due_at);
    }
}
