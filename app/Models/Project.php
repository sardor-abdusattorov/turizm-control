<?php

namespace App\Models;

use App\Enums\ContractDirection;
use App\Enums\ParticipantRole;
use App\Enums\ProjectType;
use App\Models\Concerns\HasActiveStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class Project extends Model
{
    use HasActiveStatus;
    use HasFactory;

    protected $fillable = [
        'type',
        'name',
        'venue',
        'starts_on',
        'ends_on',
        'area_sqm',
        'area_cost',
        'area_is_free',
        'area_currency_id',
        'stand_cost',
        'stand_currency_id',
        'estimate_amount',
        'final_amount',
        'attendees_count',
        'photo_report_url',
        'gallery',
        'description',
        'status',
        'created_by',
    ];

    protected $casts = [
        'type' => ProjectType::class,
        'starts_on' => 'date',
        'ends_on' => 'date',
        'area_sqm' => 'decimal:2',
        'area_cost' => 'decimal:2',
        'area_is_free' => 'boolean',
        'stand_cost' => 'decimal:2',
        'estimate_amount' => 'decimal:2',
        'final_amount' => 'decimal:2',
        'attendees_count' => 'integer',
        'gallery' => 'array',
        'status' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::deleting(function (Project $project): void {
            foreach ($project->gallery ?? [] as $path) {
                Storage::disk('local')->delete($path);
            }
        });
    }

    public function participants(): HasMany
    {
        return $this->hasMany(ProjectParticipant::class)->orderBy('sort');
    }

    public function sponsors(): HasMany
    {
        return $this->hasMany(ProjectParticipant::class)
            ->where('role', ParticipantRole::Sponsor)
            ->orderBy('sort');
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class)->orderByDesc('created_at');
    }

    /**
     * Buyruqs this project rests on, collected through its contracts (each
     * contract names its basis order) — a project may span several: the
     * annual 74-АФ plus the per-exhibition delegation order.
     *
     * @return Collection<int, Order>
     */
    public function ordersViaContracts(): Collection
    {
        return $this->contracts()
            ->whereNotNull('order_id')
            ->with('order')
            ->get()
            ->pluck('order')
            ->filter()
            ->unique('id')
            ->values();
    }

    /**
     * Sums of non-rejected contracts of the given direction, keyed by
     * currency code — the project's expense (rental + stand + services) or
     * income (fees, sponsorship) side, kept per currency because dossiers
     * mix EUR/USD/GBP and сум.
     *
     * @return array<string, float>
     */
    public function contractTotalsByCurrency(ContractDirection $direction): array
    {
        return $this->contracts()
            ->where('status', '!=', Contract::STATUS_REJECTED->value)
            ->whereHas('contractType', fn ($query) => $query->where('direction', $direction->value))
            ->with('currency')
            ->get()
            ->groupBy(fn (Contract $contract): string => $contract->currency?->short_name ?? '')
            ->map(fn ($group): float => (float) $group->sum('amount'))
            ->toArray();
    }

    public function areaCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'area_currency_id');
    }

    public function standCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'stand_currency_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Revenue of the project — the sum of participant fees. The source
     * registry stores a "profit" figure per exhibition, and it always equals
     * this sum (verified against all 16 rows of the 2025 registry), so it is
     * computed rather than stored.
     */
    public function feesTotal(): float
    {
        if ($this->relationLoaded('participants')) {
            return (float) $this->participants->sum('amount');
        }

        return (float) $this->participants()->sum('amount');
    }

    /**
     * Total actually collected across all participants (sum of their cached
     * paid_amount, maintained by ProjectPaymentObserver).
     */
    public function paidTotal(): float
    {
        if ($this->relationLoaded('participants')) {
            return (float) $this->participants->sum('paid_amount');
        }

        return (float) $this->participants()->sum('paid_amount');
    }

    /**
     * Signed temporary URLs for the gallery images, mirroring
     * Payment::screenshotUrl() — the private disk serves files only through
     * expiring links.
     *
     * @return list<string>
     */
    public function galleryUrls(): array
    {
        return array_values(array_map(
            fn (string $path): string => Storage::disk('local')->temporaryUrl($path, now()->addMinutes(30)),
            $this->gallery ?? [],
        ));
    }
}
