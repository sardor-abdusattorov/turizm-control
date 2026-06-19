<?php

namespace App\Models;

use App\Enums\ContractStatus;
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
        'language',
        'title',
        'amount',
        'status',
        'signed_at',
        'document_file',
        'document_key',
        'pdf_file',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'signed_at' => 'date',
        'status' => ContractStatus::class,
    ];

    // Constants alias the enum cases so the existing `Contract::STATUS_*` call
    // sites keep working — they now resolve to (and compare as) ContractStatus.
    public const STATUS_DRAFT = ContractStatus::Draft;

    public const STATUS_IN_REVIEW = ContractStatus::InReview;

    /** Lawyer + accountant signed off; waiting for the manager to send it to the director. */
    public const STATUS_PENDING_DIRECTOR = ContractStatus::PendingDirector;

    /** Sent to the director for the final sign-off. */
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

            $folder = "contracts/{$contract->id}";

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
        'language',
    ];

    private bool $preserveApprovedStatus = false;

    /**
     * Allow the next save to keep the APPROVED status and skip
     * invalidation. Used by the "edit approved contract" path —
     * caller must enforce the permission upstream.
     */
    public function preserveApprovedOnNextSave(): self
    {
        $this->preserveApprovedStatus = true;

        return $this;
    }

    /**
     * Run from the `updating` hook. When meaningful fields change on a
     * contract that's already past the draft stage, cancel every
     * existing approver row and drop the contract back to DRAFT so the
     * manager re-submits the queue (which rebuilds fresh pending rows
     * via ContractObserver / afterSave).
     */
    private function maybeInvalidateOnEdit(): void
    {
        if ($this->preserveApprovedStatus) {
            return;
        }

        // Mid-flow only — drafts have nothing to invalidate yet.
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

        // The hook fires *before* the update is persisted, so we modify
        // the in-flight attributes directly — status falls back to draft.
        $this->status = self::STATUS_DRAFT;
        $this->signed_at = null;

        // Snapshot the chain BEFORE invalidating so we can mirror the same
        // people, in the same order, into the fresh QUEUED rows. Falling
        // back to the org's default flow only if there's nothing to mirror.
        $previousUserIds = $this->activeApprovers()
            ->orderBy('order')
            ->pluck('user_id')
            ->all();

        // Invalidate prior approvers — keeps the audit trail, frees the queue.
        $this->invalidateAllApprovers(__('app.message.invalidated_on_edit'));

        // Rebuild fresh QUEUED rows so the manager can see the chain that
        // will run on the next submit, and so the per-approver eye-modal on
        // the View page shows the old INVALIDATED record next to the new
        // QUEUED one (matches the audit pattern seen in legacy systems).
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
        return "contracts/{$this->id}/draft.docx";
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

    /**
     * Build the approval chain from the global settings queue: for each
     * department in the configured approval flow, add its approver user
     * (head, or first active member) in order. Skips departments without
     * a usable approver and never adds the same user twice.
     */
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

    /** Every row ever attached, oldest first — for history modal. */
    public function approvers(): HasMany
    {
        return $this->hasMany(ContractApprover::class)->orderBy('order');
    }

    /** Only the rows that count toward the current workflow. */
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

    /**
     * The next approver who will (or already does) act — first row that is
     * either actively pending or still queued in the chain. Used by the
     * workflow to advance the queue and promote the next QUEUED row.
     */
    public function nextInLineApprover(): ?ContractApprover
    {
        return $this->activeApprovers()
            ->whereIn('status', [ContractApprover::STATUS_PENDING, ContractApprover::STATUS_QUEUED])
            ->orderBy('order')
            ->first();
    }

    /**
     * Mark every approver row attached to the contract as INVALIDATED.
     * Used when the contract is edited mid-flow — old rows stay for the
     * audit history, fresh pending rows are built next.
     *
     * A row that already reached a verdict (approved / rejected / returned)
     * keeps that verdict in `original_status` and keeps its own `acted_at`,
     * so the audit trail still reads "Approved · 14:30 · 'looks good'" after
     * the edit cancels it. The system reason goes to `system_comment` so the
     * approver's own `comment` is never overwritten.
     */
    public function invalidateAllApprovers(?string $note = null): int
    {
        $decided = [
            ContractApprover::STATUS_APPROVED,
            ContractApprover::STATUS_REJECTED,
            ContractApprover::STATUS_RETURNED,
        ];

        $count = 0;

        foreach ($this->approvers()->get() as $approver) {
            $hadVerdict = in_array($approver->status, $decided, true);

            $approver->update([
                'original_status' => $hadVerdict ? $approver->status : $approver->original_status,
                'status' => ContractApprover::STATUS_INVALIDATED,
                'system_comment' => $note,
                // Keep the verdict's own timestamp; only stamp the moment of
                // cancellation onto rows that never acted.
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

    /** Submit needs either an active queue OR the global settings flow to fall back on. */
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

    /** The single active user holding the director role — the final approver. */
    public function directorUser(): ?User
    {
        // whereHas (not the role() scope) so a missing role just yields null
        // instead of throwing RoleDoesNotExist.
        return User::query()
            ->whereHas('roles', fn (Builder $query) => $query->where('name', self::DIRECTOR_ROLE))
            ->where('status', User::STATUS_ACTIVE)
            ->first();
    }

    /** Has the role-based director already been added to the active chain? */
    public function hasDirectorApprover(): bool
    {
        $director = $this->directorUser();

        return $director !== null
            && $this->activeApprovers()->where('user_id', $director->id)->exists();
    }

    /**
     * True while the contract is parked with the director for final sign-off
     * (director appended and still pending/queued). Used to lock editing once
     * the lawyer + accountant stage is done.
     */
    public function isInDirectorStage(): bool
    {
        $director = $this->directorUser();

        return $director !== null
            && $this->activeApprovers()
                ->where('user_id', $director->id)
                ->whereIn('status', [ContractApprover::STATUS_PENDING, ContractApprover::STATUS_QUEUED])
                ->exists();
    }

    /**
     * Append the role-based director as the final approver once the
     * lawyer + accountant stage is complete. Returns the fresh QUEUED row, or
     * null when no director is configured or one is already in the chain (so
     * the caller can finalize as APPROVED instead).
     */
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

    /**
     * The lawyer + accountant stage is done (status PENDING_DIRECTOR) and a
     * director exists — the responsible manager (or an admin) may now hand it
     * to the director for final sign-off.
     */
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

        // Editable only before the director stage: drafts, the lawyer+accountant
        // review, and rejected. PENDING_DIRECTOR and IN_REVIEW_DIRECTOR are
        // intentionally absent, so the document freezes once it's with the director.
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

    /**
     * Roles that may see every contract. Everyone else is limited to the
     * contracts they are responsible for or appear in the approval chain of.
     */
    /** The role whose single holder is the final (director) approver. */
    public const DIRECTOR_ROLE = 'director';

    public const OVERSIGHT_ROLES = ['super_admin', self::DIRECTOR_ROLE];

    public function scopeVisibleTo(Builder $query, ?User $user = null): Builder
    {
        $user ??= auth()->user();

        if (! $user) {
            return $query->whereRaw('0 = 1');
        }

        if ($user->hasAnyRole(self::OVERSIGHT_ROLES)) {
            return $query;
        }

        return $query->where(function (Builder $scoped) use ($user): void {
            $scoped->where('responsible_id', $user->id)
                ->orWhereHas('approvers', fn (Builder $q) => $q->where('user_id', $user->id));
        });
    }
}
