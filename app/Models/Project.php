<?php

namespace App\Models;

use App\Enums\ContractDirection;
use App\Enums\ProjectType;
use App\Models\Concerns\HasActiveStatus;
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
     * The project's income contracts — participant fees and sponsorship. This
     * is the source project income is derived from now that manual participants
     * are gone: «Взнос участника» rows face a Contact, «Спонсорство» rows a
     * Sponsor, both with `direction = Income` on their type.
     */
    public function incomeContracts(): HasMany
    {
        return $this->hasMany(Contract::class)
            ->whereHas('contractType', fn (Builder $query) => $query->where('direction', ContractDirection::Income->value))
            ->orderByDesc('created_at');
    }

    /**
     * Participant-fee income contracts — «Взнос участника» deals signed with a
     * Contact (no sponsor). The «Участники» side of the project split.
     */
    public function feeContracts(): HasMany
    {
        return $this->incomeContracts()->whereNull('sponsor_id');
    }

    /**
     * Sponsorship income contracts — «Спонсорство» deals signed with a Sponsor
     * (sponsor_id set). The «Спонсоры» side of the project split.
     */
    public function sponsorshipContracts(): HasMany
    {
        return $this->hasMany(Contract::class)
            ->whereNotNull('sponsor_id')
            ->orderByDesc('created_at');
    }

    /**
     * Per-currency totals of the project's income contracts of one kind (fees
     * or sponsorship) the given user may see: one row per currency with the
     * count, pledged and paid sums, ordered by count. Rejected contracts are
     * dropped and "paid" is derived from the paid percent. Powers the
     * «Участники» / «Спонсоры» count-badge breakdowns on the project lists.
     *
     * @return Collection<int, array{currency: string, count: int, total: float, paid: float}>
     */
    public function incomeTotalsByCurrency(bool $sponsors = false, ?User $user = null): Collection
    {
        $rows = ($sponsors ? $this->sponsorshipContracts() : $this->feeContracts())
            ->visibleTo($user)
            ->where('status', '!=', Contract::STATUS_REJECTED->value)
            ->selectRaw('currency_id, COUNT(*) as contracts_count, SUM(amount) as total_amount, SUM(amount * paid_percent / 100) as total_paid')
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
                'paid' => (float) $row->total_paid,
            ])
            ->sortByDesc('count')
            ->values();
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
     * Non-rejected income contracts of the project as a collection — the source
     * of the pledged/collected income figures below. Uses the eager-loaded
     * relation when present (the export loads it) and excludes rejected
     * contracts, mirroring the expense side in contractTotalsByCurrency().
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
     * Revenue of the project — the pledged total of its income contracts
     * (participant fees + sponsorship). Formerly summed manual participants;
     * derived from contracts now that those are gone. Currency-blind by design
     * (see the docblock history): fee projects are effectively single-currency
     * and the registry "profit" equals this sum.
     */
    public function feesTotal(): float
    {
        return (float) $this->pledgedIncomeContracts()
            ->sum(fn (Contract $contract): float => (float) $contract->amount);
    }

    /**
     * Total actually collected across the project's income contracts. Contracts
     * store a paid *percent*, not an absolute figure, so the collected money is
     * derived per contract (amount * paid_percent / 100) and summed.
     */
    public function paidTotal(): float
    {
        return (float) $this->pledgedIncomeContracts()
            ->sum(fn (Contract $contract): float => $contract->paidAmount());
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
