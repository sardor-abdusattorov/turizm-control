<?php

namespace App\Models;

use App\Enums\ContractDirection;
use App\Models\Concerns\HasActiveOptions;
use App\Models\Concerns\HasActiveStatus;
use App\Models\Concerns\SumsContractsByCurrency;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Spatie\Translatable\HasTranslations;

class Contact extends Model
{
    use HasActiveOptions;
    use HasActiveStatus;
    use HasFactory;
    use HasTranslations;
    use SumsContractsByCurrency;

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
        return static::activeOptions('name', 'id');
    }

    public function bankAccounts(): HasMany
    {
        return $this->hasMany(BankAccount::class)->orderBy('sort')->orderBy('id');
    }

    /**
     * Tag each row with `is_supplier` / `is_client` booleans derived from the
     * direction of its contracts: a supplier is someone we PAY (an expense
     * contract), a client is someone who PAYS us (an income contract). A
     * counterparty can be both, or neither. Used by the contacts-list column
     * and filter — one subquery each, no per-row lookups.
     *
     * @param  Builder<Contact>  $query
     * @return Builder<Contact>
     */
    public function scopeWithRoleFlags(Builder $query): Builder
    {
        $hasDirection = fn (ContractDirection $direction) => fn (Builder $contracts) => $contracts
            ->whereHas('contractType', fn (Builder $type) => $type->where('direction', $direction->value));

        return $query
            ->withExists(['contracts as is_supplier' => $hasDirection(ContractDirection::Expense)])
            ->withExists(['contracts as is_client' => $hasDirection(ContractDirection::Income)]);
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
            ->with(['currency', 'contractType', 'project'])
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
        return $this->contractsByCurrency(
            $this->contracts()->visibleTo($user),
            withPaid: false,
        );
    }

    /**
     * This counterparty's income contracts — the participant-fee («Взнос
     * участника») deals it takes part in. Replaces the old manual project
     * participations: a contact's project involvement is now a fee contract.
     */
    public function incomeContracts(): HasMany
    {
        return $this->hasMany(Contract::class)
            ->whereHas('contractType', fn (Builder $query) => $query->where('direction', ContractDirection::Income->value));
    }

    /**
     * Per-currency totals of this counterparty's income (fee) contracts the
     * given user may see (defaults to the current one): one row per currency
     * with the count, pledged and paid sums, ordered by count. Rejected
     * contracts are dropped and "paid" is derived from the paid percent, since
     * contracts carry no absolute paid column. Powers the "projects" badge
     * breakdown on the contacts list.
     *
     * @return Collection<int, array{currency: string, count: int, total: float, paid: float}>
     */
    public function projectTotalsByCurrency(?User $user = null): Collection
    {
        return $this->contractsByCurrency(
            $this->incomeContracts()
                ->visibleTo($user)
                ->where('status', '!=', Contract::STATUS_REJECTED->value),
            withPaid: true,
        );
    }
}
