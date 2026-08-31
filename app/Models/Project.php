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

    public static function dashboardDefault(): ?self
    {
        $international = fn () => static::query()
            ->active()
            ->where('type', ProjectType::International->value);

        return $international()->has('contracts')->orderByDesc('starts_on')->first()
            ?? $international()->orderByDesc('starts_on')->first()
            ?? static::query()->active()->orderByDesc('starts_on')->first();
    }

    /** @return array<string, array<int, string>> */
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

    /** @return array<int, int> */
    public static function filteredIds(?string $type = null, ?string $year = null): array
    {
        return static::pickerQuery($type, $year)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /** @return array<int, string> */
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

    public function incomeContracts(): HasMany
    {
        return $this->hasMany(Contract::class)
            ->whereHas('contractType', fn (Builder $query) => $query->where('direction', ContractDirection::Income->value))
            ->orderByDesc('created_at');
    }

    public function feeContracts(): HasMany
    {
        return $this->incomeContracts()->whereNull('sponsor_id');
    }

    public function sponsorshipContracts(): HasMany
    {
        return $this->hasMany(Contract::class)
            ->whereNotNull('sponsor_id')
            ->orderByDesc('created_at');
    }

    /** @return Collection<int, array{currency: string, count: int, total: float, paid: float}> */
    public function incomeTotalsByCurrency(bool $sponsors = false, ?User $user = null): Collection
    {
        return $this->contractsByCurrency(
            ($sponsors ? $this->sponsorshipContracts() : $this->feeContracts())
                ->visibleTo($user)
                ->where('status', '!=', Contract::STATUS_REJECTED->value),
            withPaid: true,
        );
    }

    /** @return Collection<int, Contract> */
    public function visibleContracts(?User $user = null): Collection
    {
        return $this->contracts()
            ->visibleTo($user)
            ->with(['currency', 'contractType', 'contact', 'sponsor'])
            ->latest('id')
            ->get();
    }

    /** @return Collection<int, array{currency: string, count: int, total: float}> */
    public function visibleContractTotalsByCurrency(?User $user = null): Collection
    {
        return $this->contractsByCurrency(
            $this->contracts()->visibleTo($user),
            withPaid: false,
        );
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return array<string, float> */
    public function contractTotalsByCurrency(ContractDirection $direction): array
    {
        return $this->contracts()
            ->where('status', '!=', Contract::STATUS_REJECTED->value)
            ->whereHas('contractType', fn ($query) => $query->where('direction', $direction->value))
            ->with('currency')
            ->get()
            ->groupBy(fn (Contract $contract): string => $contract->currency?->short_name ?? '')
            ->map(fn ($group): float => (float) $group->sum('amount'))

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

    /** @return Collection<int, Contract> */
    private function pledgedIncomeContracts(): Collection
    {
        $contracts = $this->relationLoaded('incomeContracts')
            ? $this->incomeContracts
            : $this->incomeContracts()->get();

        return $contracts->reject(fn (Contract $contract): bool => $contract->status === Contract::STATUS_REJECTED);
    }

    public function feesTotal(): float
    {
        return (float) $this->pledgedIncomeContracts()
            ->sum(fn (Contract $contract): float => (float) $contract->amount);
    }

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

    /** @return list<string> */
    public function galleryImageUrls(): array
    {
        return $this->signedGalleryUrls(videos: false);
    }

    /** @return list<string> */
    public function galleryVideoUrls(): array
    {
        return $this->signedGalleryUrls(videos: true);
    }

    /** @return list<string> */
    private function signedGalleryUrls(bool $videos): array
    {
        return array_values(array_map(
            fn (string $path): string => Storage::disk('local')->temporaryUrl($path, now()->addMinutes(30)),
            array_filter($this->gallery ?? [], fn (string $path): bool => self::isVideoPath($path) === $videos),
        ));
    }
}
