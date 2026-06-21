<?php

namespace App\Models;

use App\Enums\ContractStatus;
use App\Enums\PaymentStatus;
use App\Models\Concerns\HasDocumentKey;
use App\Services\Documents\ContractPlaceholderValues;
use App\Services\Documents\TemplateFiller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class Contract extends Model
{
    use HasDocumentKey, HasFactory;

    protected $fillable = [
        'number',
        'contract_template_id',
        'order_type_id',
        'contact_id',
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

    protected static function booted(): void
    {
        static::creating(function (self $contract): void {
            if (! $contract->number) {
                $contract->number = static::generateNumber();
            }

            if (! $contract->document_key) {
                $contract->document_key = static::generateDocumentKey();
            }
        });

        static::updating(function (self $contract): void {
            $contract->maybeInvalidateOnEdit();
        });

        static::deleting(function (self $contract): void {
            foreach ([$contract->document_file, $contract->pdf_file] as $path) {
                if ($path && Storage::disk('local')->exists($path)) {
                    Storage::disk('local')->delete($path);
                }
            }

            $folder = "uploads/files/contracts/{$contract->id}";

            if (Storage::disk('local')->exists($folder)) {
                Storage::disk('local')->deleteDirectory($folder);
            }
        });
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
        'order_type_id',
        'contract_template_id',
        'document_file',
    ];

    private bool $preserveApprovedStatus = false;

    public function preserveApprovedOnNextSave(): self
    {
        $this->preserveApprovedStatus = true;

        return $this;
    }

    private function maybeInvalidateOnEdit(): void
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

        $this->invalidateAllApprovers(__('app.message.invalidated_on_edit'));

        if ($previousUserIds === []) {
            $this->buildApprovalChainFromFlow();

            return;
        }

        $order = 1;

        foreach ($previousUserIds as $userId) {
            ContractApprover::create([
                'contract_id' => $this->id,
                'user_id' => $userId,
                'order' => $order++,
                'status' => ContractApprover::STATUS_QUEUED,
            ]);
        }
    }

    /**
     * Called from the OnlyOffice save-callback after the editor finalises a
     * save (status 2 / 6). When the contract was already mid-flow, the doc
     * we just received on disk is different from what previous approvers
     * signed off on — so we reset the contract to Draft and cancel every
     * approval (preserving the active chain in the queue so the manager can
     * resubmit to the same people).
     */
    public function reinvalidateAfterDocumentEdit(): void
    {
        $this->refresh();

        if ($this->status === self::STATUS_DRAFT) {
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

        $this->invalidateAllApprovers(__('app.message.invalidated_on_document_save'));

        $order = 1;

        foreach ($previousUserIds as $userId) {
            ContractApprover::create([
                'contract_id' => $this->id,
                'user_id' => $userId,
                'order' => $order++,
                'status' => ContractApprover::STATUS_QUEUED,
            ]);
        }
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

    public function documentPath(): string
    {
        return "uploads/files/contracts/{$this->id}/draft.docx";
    }

    public function documentAbsolutePath(): string
    {
        return Storage::disk('local')->path($this->documentPath());
    }

    public function documentExists(): bool
    {
        return Storage::disk('local')->exists($this->documentPath());
    }

    public function buildDocumentFromTemplate(TemplateFiller $filler, ContractPlaceholderValues $values): void
    {
        $template = $this->template;

        if (! $template || ! $template->template_file) {
            return;
        }

        $templateAbsolute = Storage::disk('local')->path($template->template_file);

        if (! is_file($templateAbsolute)) {
            return;
        }

        $filler->fill($templateAbsolute, $this->documentAbsolutePath(), $values->for($this));

        $this->update([
            'document_file' => $this->documentPath(),
            'document_key' => static::generateDocumentKey(),
        ]);
    }

    public function buildApprovalChainFromFlow(): int
    {
        $order = 1;
        $created = 0;
        $seen = [];

        foreach (Department::approvalFlow() as $code) {
            $user = Department::findByCode($code)?->approverUser();

            if (! $user || in_array($user->id, $seen, true)) {
                continue;
            }

            ContractApprover::create([
                'contract_id' => $this->id,
                'user_id' => $user->id,
                'order' => $order++,
                'status' => ContractApprover::STATUS_QUEUED,
            ]);

            $seen[] = $user->id;
            $created++;
        }

        return $created;
    }

    /**
     * Preview of the approval chain the global flow would produce, as
     * "Department — User" lines, for display on the create form.
     *
     * @return array<int, string>
     */
    public static function approvalChainPreview(): array
    {
        $rows = [];
        $position = 1;
        $seen = [];

        foreach (Department::approvalFlow() as $code) {
            $department = Department::findByCode($code);
            $user = $department?->approverUser();

            if (! $user || in_array($user->id, $seen, true)) {
                continue;
            }

            $deptName = $department->getTranslation('name', app()->getLocale());
            $rows[] = "{$position}. {$deptName} — {$user->name}";
            $seen[] = $user->id;
            $position++;
        }

        return $rows;
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ContractTemplate::class, 'contract_template_id');
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

    public function activeApprovers(): HasMany
    {
        return $this->hasMany(ContractApprover::class)
            ->whereNotIn('status', [ContractApprover::STATUS_INVALIDATED, ContractApprover::STATUS_SKIPPED])
            ->orderBy('order');
    }

    public function currentApprover(): ?ContractApprover
    {
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

        $count = 0;

        foreach ($this->approvers()->get() as $approver) {
            $hadVerdict = in_array($approver->status, $decided, true);

            $approver->update([
                'original_status' => $hadVerdict ? $approver->status : $approver->original_status,
                'status' => ContractApprover::STATUS_INVALIDATED,
                'system_comment' => $note,
                'acted_at' => $hadVerdict ? $approver->acted_at : now(),
            ]);

            $count++;
        }

        return $count;
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

        return $user
            && $this->status === self::STATUS_DRAFT
            && ($this->responsible_id === $user->id || $user->hasRole('super_admin'))
            && ($this->activeApprovers()->exists() || $this->canRebuildChainOnSubmit());
    }

    public function canBeApprovedBy(?User $user = null): bool
    {
        $user ??= auth()->user();

        return $user
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

        if ($user->hasRole('super_admin') || $user->can('view_all_contracts')) {
            return true;
        }

        if ($this->responsible_id === $user->id) {
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

        // A finalized contract is immutable — once it is fully approved the
        // signed document is locked for everyone, administrators included.
        if ($this->status === self::STATUS_APPROVED) {
            return false;
        }

        if ($user->hasRole('super_admin')) {
            return true;
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

    public function canBeDeletedBy(?User $user = null): bool
    {
        $user ??= auth()->user();

        return $user
            && $this->status === self::STATUS_DRAFT
            && ($this->responsible_id === $user->id || $user->hasRole('super_admin'));
    }

    public function scopeAwaitingApprovalBy(Builder $query, ?User $user = null): Builder
    {
        $user ??= auth()->user();

        if (! $user) {
            return $query->whereRaw('0 = 1');
        }

        return $query
            ->where('status', self::STATUS_IN_REVIEW)
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

    public const DIRECTOR_ROLE = 'director';

    public const OVERSIGHT_ROLES = ['super_admin', self::DIRECTOR_ROLE];

    public function scopeVisibleTo(Builder $query, ?User $user = null): Builder
    {
        $user ??= auth()->user();

        if (! $user) {
            return $query->whereRaw('0 = 1');
        }

        if ($user->hasAnyRole(self::OVERSIGHT_ROLES) || $user->can('view_all_contracts')) {
            return $query;
        }

        return $query->where(function (Builder $scoped) use ($user): void {
            $scoped->where('responsible_id', $user->id)
                ->orWhereHas('approvers', fn (Builder $q) => $q->where('user_id', $user->id));
        });
    }
}
