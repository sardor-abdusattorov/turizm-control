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
