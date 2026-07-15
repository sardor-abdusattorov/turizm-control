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

    /**
     * The project the dashboard opens on before the user picks one: the
     * nearest upcoming active project, falling back to the latest past one.
     */
    public static function dashboardDefault(): ?self
    {
        return static::query()
            ->active()
            ->whereDate('starts_on', '>=', today())
            ->orderBy('starts_on')
            ->first()
            ?? static::query()->active()->orderByDesc('starts_on')->first();
    }

    /**
     * Active projects as «тип · год» optgroups (newest first), optionally
     * narrowed to one year — shared by the contract form and the dashboard
     * project picker.
     *
     * @return array<string, array<int, string>>
     */
    public static function groupedOptions(?string $year = null): array
    {
        return static::query()
            ->active()
            ->when($year, fn ($query) => $query->whereYear('starts_on', $year))
            ->orderByDesc('starts_on')
            ->orderByDesc('id')
            ->get()
            ->groupBy(fn (self $project): string => trim(
                $project->type->label().($project->starts_on ? ' · '.$project->starts_on->year : ''),
            ))
            ->map(fn ($group) => $group->mapWithKeys(
                fn (self $project): array => [$project->id => $project->name],
            )->toArray())
            ->toArray();
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
     * The project contracts the given user (defaults to the current one) is
     * allowed to see — the same visibility rule as the count badge and the
     * page tab, so a manager without oversight only ever sees their own.
     *
     * @return Collection<int, Contract>
     */
    public function visibleContracts(?User $user = null): Collection
    {
        return $this->contracts()
            ->visibleTo($user)
            ->with(['currency', 'contractType'])
            ->latest('id')
            ->get();
    }

    /**
     * Per-currency contract totals of this project, limited to the contracts
     * $user may see: one row per currency with the count and summed amount,
     * ordered by count. Powers the contracts badge breakdown on the lists.
     *
     * @return Collection<int, array{currency: string, count: int, total: float}>
     */
    public function visibleContractTotalsByCurrency(?User $user = null): Collection
    {
        $rows = $this->contracts()
            ->visibleTo($user)
            ->selectRaw('currency_id, COUNT(*) as contracts_count, SUM(amount) as total_amount')
            ->groupBy('currency_id')
            ->get();

        $currencies = Currency::query()
            ->whereIn('id', $rows->pluck('currency_id')->filter())
            ->pluck('short_name', 'id');

        return $rows
            ->map(fn (Contract $row): array => [
                'currency' => $currencies->get($row->currency_id) ?? '—',
                'count' => (int) $row->contracts_count,
                'total' => (float) $row->total_amount,
            ])
            ->sortByDesc('count')
            ->values();
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
     * Per-currency totals of this project's participants of one role: one row
     * per currency with the count, pledged and paid sums, ordered by count.
     * Powers the participants / sponsors badge breakdowns on the project
     * lists — mixed currencies stay apart, never converted.
     *
     * @return Collection<int, array{currency: string, count: int, total: float, paid: float}>
     */
    public function participantTotalsByCurrency(ParticipantRole $role): Collection
    {
        return $this->participants
            ->where('role', $role)
            ->groupBy(fn (ProjectParticipant $p): string => $p->currency?->short_name ?? '—')
            ->map(fn ($group, string $currency): array => [
                'currency' => $currency,
                'count' => $group->count(),
                'total' => (float) $group->sum('amount'),
                'paid' => (float) $group->sum('paid_amount'),
            ])
            ->sortByDesc('count')
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
