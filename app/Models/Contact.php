<?php

namespace App\Models;

use App\Models\Concerns\HasActiveStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Spatie\Translatable\HasTranslations;

class Contact extends Model
{
    use HasActiveStatus;
    use HasFactory;
    use HasTranslations;

    public $translatable = ['name', 'address'];

    protected $fillable = [
        'type',
        'legal_form',
        'name',
        'inn',
        'pinfl',
        'oked',
        'address',
        'phone',
        'email',
        'website',
        'contact_person',
        'director_name',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    public const TYPE_LEGAL = 'legal';

    public const TYPE_INDIVIDUAL = 'individual';

    public static function getTypes(): array
    {
        return [
            self::TYPE_LEGAL => __('app.contact.type.legal'),
            self::TYPE_INDIVIDUAL => __('app.contact.type.individual'),
        ];
    }

    public static function getTypeColors(): array
    {
        return [
            self::TYPE_LEGAL => 'info',
            self::TYPE_INDIVIDUAL => 'warning',
        ];
    }

    /**
     * Active contacts as id => localized name pairs (for Select::options).
     *
     * @return array<int, string>
     */
    public static function getActive(): array
    {
        return static::query()
            ->active()
            ->orderBy('id')
            ->get()
            ->mapWithKeys(fn (self $item) => [
                $item->id => $item->getTranslation('name', app()->getLocale()),
            ])
            ->toArray();
    }

    public function bankAccounts(): HasMany
    {
        return $this->hasMany(BankAccount::class)->orderBy('sort')->orderBy('id');
    }

    /**
     * The bank account to quote for a deal in the given currency: the exact
     * currency match first, then a currency-agnostic account, then whatever
     * comes first. Null when the counterparty has no accounts on file.
     */
    public function bankAccountFor(?int $currencyId = null): ?BankAccount
    {
        $accounts = $this->relationLoaded('bankAccounts')
            ? $this->bankAccounts
            : $this->bankAccounts()->get();

        return $accounts->firstWhere('currency_id', $currencyId)
            ?? $accounts->firstWhere('currency_id', null)
            ?? $accounts->first();
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    /**
     * The contracts of this counterparty the given user (defaults to the
     * current one) is allowed to see — newest first — for the breakdown
     * modal. Same visibility rule as the count badge and the totals, so a
     * manager without oversight only ever sees their own.
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
     * Per-currency contract totals for this counterparty, limited to the
     * contracts $user is allowed to see (defaults to the current user): one row
     * per currency with the contract count and the summed amount, ordered by
     * count. Powers the "contracts" badge breakdown on the contacts list.
     *
     * @return Collection<int, array{currency: string, count: int, total: float}>
     */
    public function contractTotalsByCurrency(?User $user = null): Collection
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

    public function projectParticipations(): HasMany
    {
        return $this->hasMany(ProjectParticipant::class);
    }

    /**
     * Per-currency totals of this counterparty's project participations: one
     * row per currency with the count, pledged and paid sums, ordered by
     * count. Powers the "projects" badge breakdown on the contacts list.
     *
     * @return Collection<int, array{currency: string, count: int, total: float, paid: float}>
     */
    public function projectTotalsByCurrency(): Collection
    {
        $rows = $this->projectParticipations()
            ->selectRaw('currency_id, COUNT(*) as participations_count, SUM(amount) as total_amount, SUM(paid_amount) as total_paid')
            ->groupBy('currency_id')
            ->get();

        $currencies = Currency::query()
            ->whereIn('id', $rows->pluck('currency_id')->filter())
            ->pluck('short_name', 'id');

        return $rows
            ->map(fn (ProjectParticipant $row): array => [
                'currency' => $currencies->get($row->currency_id) ?? '—',
                'count' => (int) $row->participations_count,
                'total' => (float) $row->total_amount,
                'paid' => (float) $row->total_paid,
            ])
            ->sortByDesc('count')
            ->values();
    }
}
