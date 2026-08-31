<?php

namespace App\Models;

use App\Enums\RequisitionStatus;
use Database\Factories\RequisitionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A request the supply officer («завхоз») has to check: an author writes it,
 * names who reviews it, and the review carries a deadline taken from settings
 * at submit time.
 */
class Requisition extends Model
{
    /** @use HasFactory<RequisitionFactory> */
    use HasFactory;

    protected $fillable = [
        'number',
        'title',
        'description',
        'project_id',
        'author_id',
        'reviewer_id',
        'status',
        'submitted_at',
        'due_at',
        'reviewed_at',
        'review_comment',
    ];

    protected $casts = [
        'status' => RequisitionStatus::class,
        'submitted_at' => 'datetime',
        'due_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public const NUMBER_PREFIX = 'ЗВ';

    public static function nextNumber(): string
    {
        $year = now()->year;
        $prefix = self::NUMBER_PREFIX.'-'.$year.'-';

        $last = static::query()
            ->where('number', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('number');

        $sequence = $last ? ((int) substr((string) $last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Working days the supply officer gets, from settings — clamped so a
     * mistyped setting can never produce a deadline in the past.
     */
    public static function reviewDays(): int
    {
        return max(1, (int) settings('requisition.review_days', 3));
    }

    public static function defaultReviewerId(): ?int
    {
        $id = settings('requisition.reviewer_id');

        return $id ? (int) $id : null;
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function isOverdue(): bool
    {
        return $this->status === RequisitionStatus::InReview
            && $this->due_at !== null
            && now()->greaterThan($this->due_at);
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [RequisitionStatus::Draft, RequisitionStatus::Rejected], true);
    }

    public function canBeSubmittedBy(?User $user = null): bool
    {
        $user ??= auth()->user();

        return $user !== null
            && $this->isEditable()
            && $this->reviewer_id !== null
            && ($this->author_id === $user->id || $user->hasRole('super_admin'));
    }

    public function canBeReviewedBy(?User $user = null): bool
    {
        $user ??= auth()->user();

        return $user !== null
            && $this->status === RequisitionStatus::InReview
            && ($this->reviewer_id === $user->id || $user->hasRole('super_admin'));
    }

    public function canBeEditedBy(?User $user = null): bool
    {
        $user ??= auth()->user();

        return $user !== null
            && $this->isEditable()
            && ($this->author_id === $user->id || $user->hasRole('super_admin'));
    }

    /**
     * Everyone sees their own requisitions and the ones waiting on them.
     * Oversight — anyone holding `view_all_requisitions` — sees the registry
     * whole.
     *
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
            ->orWhere('reviewer_id', $user->id));
    }
}
