<?php

namespace App\Models;

use App\Enums\ParticipantRole;
use App\Enums\ProjectType;
use App\Models\Concerns\HasActiveStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        'order_id',
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

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class)->orderByDesc('created_at');
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
