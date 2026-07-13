<?php

namespace App\Models;

use App\Observers\ProjectPaymentObserver;
use Database\Factories\ProjectPaymentFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * A single installment a project participant paid against their pledged fee.
 * Mirrors the contract Payment model — the only conceptual swap is
 * percent-of-contract → absolute amount in the participant's own currency.
 */
#[ObservedBy(ProjectPaymentObserver::class)]
class ProjectPayment extends Model
{
    /** @use HasFactory<ProjectPaymentFactory> */
    use HasFactory;

    protected $fillable = [
        'project_participant_id',
        'created_by',
        'amount',
        'paid_at',
        'screenshot',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'date',
    ];

    public const SCREENSHOT_DIR = 'project-payments';

    protected static function booted(): void
    {
        static::creating(function (self $payment): void {
            // In HTTP context the creator is always the authenticated user —
            // overriding (not just defaulting) so a mass-assigned `created_by`
            // cannot spoof attribution. CLI / seeders keep what they passed.
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

    public function participant(): BelongsTo
    {
        return $this->belongsTo(ProjectParticipant::class, 'project_participant_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
