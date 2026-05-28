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
    ];

    protected $casts = [
        'order' => 'integer',
        'acted_at' => 'datetime',
    ];

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_SKIPPED = 'skipped';

    public static function getStatuses(): array
    {
        return [
            self::STATUS_PENDING => __('app.contract_approver.status.pending'),
            self::STATUS_APPROVED => __('app.contract_approver.status.approved'),
            self::STATUS_REJECTED => __('app.contract_approver.status.rejected'),
            self::STATUS_SKIPPED => __('app.contract_approver.status.skipped'),
        ];
    }

    public static function getStatusColors(): array
    {
        return [
            self::STATUS_PENDING => 'warning',
            self::STATUS_APPROVED => 'success',
            self::STATUS_REJECTED => 'danger',
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

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
