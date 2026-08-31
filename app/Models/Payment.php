<?php

namespace App\Models;

use App\Observers\PaymentObserver;
use App\Support\Money;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[ObservedBy(PaymentObserver::class)]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    protected $fillable = [
        'contract_id',
        'project_id',
        'created_by',
        'percent',
        'amount',
        'currency_id',
        'purpose',
        'paid_at',
        'screenshots',
    ];

    protected $casts = [
        'percent' => 'decimal:2',
        'amount' => 'decimal:2',
        'paid_at' => 'date',
        'screenshots' => 'array',
    ];

    public const SCREENSHOT_DIR = 'payments';

    protected static function booted(): void
    {
        static::creating(function (self $payment): void {
            // In HTTP context the creator is always the authenticated user —
            // overriding (not just defaulting) so a mass-assigned
            // `created_by` from the request body cannot spoof attribution.
            // CLI / seeders / factories keep whatever they passed in.
            if (! app()->runningInConsole() && auth()->check()) {
                $payment->created_by = (int) auth()->id();

                return;
            }

            if (! $payment->created_by && auth()->check()) {
                $payment->created_by = (int) auth()->id();
            }
        });

        static::deleting(function (self $payment): void {
            foreach ($payment->screenshots ?? [] as $path) {
                if (Storage::disk('local')->exists($path)) {
                    Storage::disk('local')->delete($path);
                }
            }
        });
    }

    public static function isPdf(string $path): bool
    {
        return str_ends_with(strtolower($path), '.pdf');
    }

    /**
     * The proof files as signed expiring links, split by kind for the views:
     * images go to thumbnails/lightboxes, PDFs to plain document links.
     *
     * @return list<array{url: string, name: string, pdf: bool}>
     */
    public function screenshotFiles(): array
    {
        return array_values(array_map(
            fn (string $path): array => [
                'url' => Storage::disk('local')->temporaryUrl($path, now()->addMinutes(30)),
                'name' => basename($path),
                'pdf' => self::isPdf($path),
            ],
            $this->screenshots ?? [],
        ));
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Whether this payment settles a project directly rather than a contract.
     */
    public function isDirect(): bool
    {
        return $this->contract_id === null;
    }

    /**
     * The project the money belongs to: named outright on a direct payment,
     * inherited from the contract otherwise.
     */
    public function resolvedProject(): ?Project
    {
        return $this->project ?? $this->contract?->project;
    }

    public function subjectLabel(): string
    {
        return $this->isDirect()
            ? ($this->project?->name ?? __('app.label.not_set'))
            : trim(($this->contract?->number ?? '').' · '.($this->contract?->title ?? ''), ' ·');
    }

    /**
     * What the payment is worth as one line: a share for a contract payment,
     * a sum in its own currency for a direct one.
     */
    public function valueLabel(): string
    {
        if (! $this->isDirect()) {
            return format_percent((float) $this->percent).'%';
        }

        return Money::format($this->amount).' '.($this->currency?->short_name ?? '');
    }

    public function scopeVisibleTo(Builder $query, ?User $user = null): Builder
    {
        $user ??= auth()->user();

        if (! $user) {
            return $query->whereRaw('0 = 1');
        }

        // Same rule as contracts: oversight roles and anyone granted the
        // `view_all_contracts` permission see every payment. Everyone else —
        // managers included — only the payments on contracts they are
        // responsible for. Access is permission-driven, not tied to a role.
        if ($user->hasAnyRole(Contract::OVERSIGHT_ROLES) || $user->can('view_all_contracts')) {
            return $query;
        }

        // A direct project payment has no responsible contract to inherit
        // visibility from, so it stays with whoever filed it.
        return $query->where(fn (Builder $inner) => $inner
            ->whereHas('contract', fn (Builder $contract) => $contract->where('responsible_id', $user->id))
            ->orWhere(fn (Builder $direct) => $direct
                ->whereNull('contract_id')
                ->where('created_by', $user->id)));
    }
}
