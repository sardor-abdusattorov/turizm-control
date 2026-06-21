<?php

namespace App\Models;

use App\Observers\PaymentObserver;
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
        'created_by',
        'percent',
        'paid_at',
        'screenshot',
    ];

    protected $casts = [
        'percent' => 'decimal:2',
        'paid_at' => 'date',
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
            if ($payment->screenshot && Storage::disk('local')->exists($payment->screenshot)) {
                Storage::disk('local')->delete($payment->screenshot);
            }
        });
    }

    public function screenshotUrl(): ?string
    {
        return $this->screenshot
            ? Storage::disk('local')->temporaryUrl($this->screenshot, now()->addMinutes(30))
            : null;
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeVisibleTo(Builder $query, ?User $user = null): Builder
    {
        $user ??= auth()->user();

        if (! $user) {
            return $query->whereRaw('0 = 1');
        }

        if ($user->hasAnyRole(Contract::OVERSIGHT_ROLES) || $user->can('view_all_contracts')) {
            return $query;
        }

        if ($user->hasRole('accountant')) {
            return $query;
        }

        return $query->whereHas('contract', fn (Builder $q) => $q->where('responsible_id', $user->id));
    }
}
