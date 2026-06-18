<?php

namespace App\Models;

use App\Services\Documents\ContractPlaceholderValues;
use App\Services\Documents\TemplateFiller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Contract extends Model
{
    use HasFactory;

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

        // Mid-flow only — drafts and already-cancelled rejected ones don't
        // have anything to invalidate yet, archived is read-only.
        if (! in_array($this->getOriginal('status'), [
            self::STATUS_IN_REVIEW,
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

        // Invalidate prior approvers — keeps the audit trail, frees the
        // queue. Fresh rows are NOT rebuilt here: the manager re-submits,
        // and at submit time the queue is read from settings as usual.
        $this->invalidateAllApprovers(__('app.message.invalidated_on_edit'));
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

    public static function generateDocumentKey(): string
    {
        return Str::random(20);
    }

    public function refreshDocumentKey(): void
    {
        $this->update(['document_key' => static::generateDocumentKey()]);
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
                'status' => ContractApprover::STATUS_PENDING,
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

    public function buildApproverChain(): int
    {
        $manager = $this->responsible;

        if (! $manager) {
            return 0;
        }

        $flow = Department::approvalFlow();

        $recipients = $manager->defaultRecipients()
            ->where('users.status', User::STATUS_ACTIVE)
            ->with('department')
            ->get()
            ->filter(fn (User $user): bool => $user->department?->isApproverDepartment() ?? false)
            ->groupBy(fn (User $user): string => (string) $user->department?->code);

        $created = 0;
        $order = 1;

        foreach ($flow as $code) {
            foreach ($recipients->get($code, collect()) as $recipient) {
                ContractApprover::create([
                    'contract_id' => $this->id,
                    'user_id' => $recipient->id,
                    'order' => $order++,
                    'status' => ContractApprover::STATUS_PENDING,
                ]);

                $created++;
            }
        }

        return $created;
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
     * Mark every approver row attached to the contract as INVALIDATED.
     * Used when the contract is edited mid-flow — old rows stay for the
     * audit history, fresh pending rows are built next.
     */
    public function invalidateAllApprovers(?string $note = null): int
    {
        return $this->approvers()->update([
            'status' => ContractApprover::STATUS_INVALIDATED,
            'comment' => $note,
            'acted_at' => now(),
        ]);
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

    public function wasRejected(): bool
    {
        return $this->activeApprovers()->where('status', ContractApprover::STATUS_REJECTED)->exists();
    }

    public function resetApprovalState(): void
    {
        $this->approvers()->update([
            'status' => ContractApprover::STATUS_PENDING,
            'comment' => null,
            'acted_at' => null,
        ]);

        $this->update(['status' => self::STATUS_DRAFT]);
    }

    public function resetApproversAfterOrder(int $order): void
    {
        $this->approvers()
            ->where('order', '>', $order)
            ->update([
                'status' => ContractApprover::STATUS_PENDING,
                'comment' => null,
                'acted_at' => null,
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
            && $this->status === self::STATUS_IN_REVIEW
            && $this->isCurrentApprover($user);
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
}
