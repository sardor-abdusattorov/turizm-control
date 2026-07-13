<?php

namespace App\Models;

use App\Enums\ParticipantRole;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectParticipant extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'contact_id',
        'sponsor_id',
        'role',
        'name',
        'amount',
        'paid_amount',
        'currency_id',
        'sort',
    ];

    protected $casts = [
        'role' => ParticipantRole::class,
        'amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function sponsor(): BelongsTo
    {
        return $this->belongsTo(Sponsor::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(ProjectPayment::class)->orderByDesc('paid_at');
    }

    public function paidAmount(): float
    {
        return (float) $this->paid_amount;
    }

    public function remainingAmount(): float
    {
        return max(0, round((float) $this->amount - $this->paidAmount(), 2));
    }

    public function isFullyPaid(): bool
    {
        return $this->paidAmount() + 0.001 >= (float) $this->amount;
    }

    /**
     * Payment status derived from paid-vs-pledged, reusing the contract
     * PaymentStatus enum by feeding it a ratio. A zero pledge counts as
     * settled (nothing owed) so it never shows a red "not paid" pill.
     */
    public function paymentStatus(): PaymentStatus
    {
        $pledge = (float) $this->amount;

        return PaymentStatus::fromPercent($pledge > 0 ? $this->paidAmount() / $pledge * 100 : 100.0);
    }
}
