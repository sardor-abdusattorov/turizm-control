<?php

namespace App\Models;

use App\Enums\ContractDirection;
use App\Enums\ProjectType;
use App\Models\Concerns\HasActiveStatus;
use App\Models\Concerns\SumsContractsByCurrency;
use Illuminate\Database\Eloquent\Builder;
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
    use SumsContractsByCurrency;

    protected $fillable = [
        'type',
        'name',
        'order_id',
        'venue',
        'starts_on',
        'ends_on',
        'area_sqm',
        'area_cost',
        'area_is_free',
        'area_currency_id',
        'stand_cost',
        'stand_currency_id',
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
     * narrowed to one year and/or one type — shared by the contract form and
     * the dashboard project picker.
     *
     * @return array<string, array<int, string>>
     */
    public static function groupedOptions(?string $year = null, ?string $type = null): array
    {
        return static::pickerQuery($type, $year)
            ->get()
            ->groupBy(fn (self $project): string => trim(
                $project->type->label().($project->starts_on ? ' · '.$project->starts_on->year : ''),
            ))
            ->map(fn ($group) => $group->mapWithKeys(
                fn (self $project): array => [$project->id => $project->name],
            )->toArray())
            ->toArray();
    }

    /**
     * The active project ids matching a type/year filter, newest first — used
     * by the dashboard picker to re-home the selection when a filter changes.
     *
     * @return array<int, int>
     */
    public static function filteredIds(?string $type = null, ?string $year = null): array
    {
        return static::pickerQuery($type, $year)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * The distinct years that active projects start in, newest first — the
     * option set for the dashboard year filter.
     *
     * @return array<int, string>
     */
    public static function pickerYears(): array
    {
        return static::query()
            ->active()
            ->whereNotNull('starts_on')
            ->get()
            ->map(fn (self $project): int => $project->starts_on->year)
            ->unique()
            ->sortDesc()
            ->values()
            ->map(fn (int $year): string => (string) $year)
            ->all();
    }

    /**
     * Shared base query for the picker helpers: active projects, optionally
     * narrowed by type and/or year, ordered newest first.
     */
    protected static function pickerQuery(?string $type = null, ?string $year = null): Builder
    {
        return static::query()
            ->active()
            ->when($year, fn (Builder $query) => $query->whereYear('starts_on', $year))
            ->when($type, fn (Builder $query) => $query->where('type', $type))
            ->orderByDesc('starts_on')
            ->orderByDesc('id');
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class)->orderByDesc('created_at');
    }

    /**
     * Income contracts — participant fees (a Contact) and sponsorship
     * (a Sponsor); the source all project income figures derive from.
     */
    public function incomeContracts(): HasMany
    {
        return $this->hasMany(Contract::class)
            ->whereHas('contractType', fn (Builder $query) => $query->where('direction', ContractDirection::Income->value))
            ->orderByDesc('created_at');
    }

    /**
     * Participant-fee income contracts — deals signed with a Contact.
     */
    public function feeContracts(): HasMany
    {
        return $this->incomeContracts()->whereNull('sponsor_id');
    }

    /**
     * Sponsorship income contracts — deals signed with a Sponsor.
     */
    public function sponsorshipContracts(): HasMany
    {
        return $this->hasMany(Contract::class)
            ->whereNotNull('sponsor_id')
            ->orderByDesc('created_at');
    }

    /**
     * Per-currency totals of one income kind (fees or sponsorship) the given
     * user may see; rejected contracts drop out, "paid" is derived from the
     * paid percent. Powers the count-badge breakdowns on the project lists.
     *
     * @return Collection<int, array{currency: string, count: int, total: float, paid: float}>
     */
    public function incomeTotalsByCurrency(bool $sponsors = false, ?User $user = null): Collection
    {
        return $this->contractsByCurrency(
            ($sponsors ? $this->sponsorshipContracts() : $this->feeContracts())
                ->visibleTo($user)
                ->where('status', '!=', Contract::STATUS_REJECTED->value),
            withPaid: true,
        );
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
            ->with(['currency', 'contractType', 'contact', 'sponsor'])
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
        return $this->contractsByCurrency(
            $this->contracts()->visibleTo($user),
            withPaid: false,
        );
    }

    /**
     * The buyruq (приказ) the participation rests on — «на основании приказа
     * № 119-АФ». Lives on the project; its contracts inherit the basis.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
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
            // Alphabetical keys keep the totals stable across DB engines —
            // fetch order without it differs between platforms.
            ->sortKeys()
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
     * Non-rejected income contracts, reusing the eager-loaded relation when
     * present (the export loads it).
     *
     * @return Collection<int, Contract>
     */
    private function pledgedIncomeContracts(): Collection
    {
        $contracts = $this->relationLoaded('incomeContracts')
            ? $this->incomeContracts
            : $this->incomeContracts()->get();

        return $contracts->reject(fn (Contract $contract): bool => $contract->status === Contract::STATUS_REJECTED);
    }

    /**
     * Pledged total of the income contracts. Currency-blind by design: fee
     * projects are effectively single-currency and the registry "profit"
     * equals this sum.
     */
    public function feesTotal(): float
    {
        return (float) $this->pledgedIncomeContracts()
            ->sum(fn (Contract $contract): float => (float) $contract->amount);
    }

    /**
     * Total actually collected. Contracts store a paid *percent*, so the money
     * is derived per contract (amount * paid_percent / 100) and summed.
     */
    public function paidTotal(): float
    {
        return (float) $this->pledgedIncomeContracts()
            ->sum(fn (Contract $contract): float => $contract->paidAmount());
    }

    public static function isVideoPath(string $path): bool
    {
        return in_array(
            strtolower(pathinfo($path, PATHINFO_EXTENSION)),
            ['mp4', 'mov', 'avi', 'webm', 'mkv', 'm4v'],
            true,
        );
    }

    /**
     * @return list<string>
     */
    public function galleryImageUrls(): array
    {
        return $this->signedGalleryUrls(videos: false);
    }

    /**
     * @return list<string>
     */
    public function galleryVideoUrls(): array
    {
        return $this->signedGalleryUrls(videos: true);
    }

    /**
     * Signed temporary URLs for the gallery, mirroring
     * Payment::screenshotFiles() — the private disk serves files only through
     * expiring links.
     *
     * @return list<string>
     */
    private function signedGalleryUrls(bool $videos): array
    {
        return array_values(array_map(
            fn (string $path): string => Storage::disk('local')->temporaryUrl($path, now()->addMinutes(30)),
            array_filter($this->gallery ?? [], fn (string $path): bool => self::isVideoPath($path) === $videos),
        ));
    }
}
