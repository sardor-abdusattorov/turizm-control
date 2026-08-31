<?php

namespace App\Models;

use App\Enums\ApprovalStatus;
use App\Enums\RequisitionStatus;
use App\Models\Concerns\HasApprovals;
use Database\Factories\RequisitionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Requisition extends Model
{
    /** @use HasFactory<RequisitionFactory> */
    use HasApprovals;

    use HasFactory;

    protected $fillable = [
        'number',
        'title',
        'description',
        'project_id',
        'author_id',
        'status',
        'submitted_at',
    ];

    protected $casts = [
        'status' => RequisitionStatus::class,
        'submitted_at' => 'datetime',
    ];

    public const NUMBER_PREFIX = 'ЗВ';

    public static function nextNumber(): string
    {
        $prefix = self::NUMBER_PREFIX.'-'.now()->year.'-';

        $last = static::query()
            ->where('number', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('number');

        $sequence = $last ? ((int) substr((string) $last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
    }

    public static function reviewDays(): int
    {
        return max(1, (int) settings('requisition.review_days', 3));
    }

    /** @return array<int, int> */
    public static function defaultApproverIds(): array
    {
        $configured = settings('requisition.approver_ids', []);

        return array_values(array_filter(array_map('intval', (array) $configured)));
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function isOverdue(): bool
    {
        return (bool) $this->currentApproval()?->isOverdue();
    }

    public function canBeEditedBy(?User $user = null): bool
    {
        $user ??= auth()->user();

        return $user !== null
            && $this->status->isEditable()
            && ($this->author_id === $user->id || $user->hasRole('super_admin'));
    }

    public function canBeRecalledBy(?User $user = null): bool
    {
        $user ??= auth()->user();

        return $user !== null
            && in_array($this->status, [RequisitionStatus::InReview, RequisitionStatus::Rejected], true)
            && ($this->author_id === $user->id || $user->hasRole('super_admin'));
    }

    public function canBeSubmittedBy(?User $user = null): bool
    {
        $user ??= auth()->user();

        return $user !== null
            && $this->status === RequisitionStatus::Draft
            && $this->hasApprovalChain()
            && ($this->author_id === $user->id || $user->hasRole('super_admin'));
    }

    /**
     * @param  Builder<Requisition>  $query
     * @return Builder<Requisition>
     */
    public function scopeVisibleTo(Builder $query, ?User $user = null): Builder
    {
        $user ??= auth()->user();

        if (! $user) {
            return $query->whereRaw('0 = 1');
        }

        if ($user->can('view_all_requisitions')) {
            return $query;
        }

        return $query->where(fn (Builder $inner) => $inner
            ->where('author_id', $user->id)
            ->orWhereHas('approvals', fn (Builder $approvals) => $approvals->where('user_id', $user->id)));
    }

    /**
     * @param  Builder<Requisition>  $query
     * @return Builder<Requisition>
     */
    public function scopeAwaiting(Builder $query, ?User $user = null): Builder
    {
        $user ??= auth()->user();

        return $query->whereHas('approvals', fn (Builder $approvals) => $approvals
            ->where('user_id', $user?->id)
            ->where('status', ApprovalStatus::Pending));
    }
}
