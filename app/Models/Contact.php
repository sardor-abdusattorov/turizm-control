<?php

namespace App\Models;

use App\Enums\ContractDirection;
use App\Models\Concerns\HasActiveOptions;
use App\Models\Concerns\HasActiveStatus;
use App\Models\Concerns\SearchesByTaxId;
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
    use SearchesByTaxId;
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

    /** @return array<int, string> */
    public static function getActive(): array
    {
        return static::activeOptions('name', 'id');
    }

    /** @return array<string, string> */
    protected static function taxIdColumns(): array
    {
        return [
            'inn' => 'app.label.inn',
            'pinfl' => 'app.label.pinfl',
        ];
    }

    protected function searchableName(): string
    {
        return $this->getTranslation('name', app()->getLocale());
    }

    public function bankAccounts(): HasMany
    {
        return $this->hasMany(BankAccount::class)->orderBy('sort')->orderBy('id');
    }

    /**
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

    /** @return Collection<int, Contract> */
    public function visibleContracts(?User $user = null): Collection
    {
        return $this->contracts()
            ->visibleTo($user)
            ->with(['currency', 'contractType', 'project'])
            ->latest('id')
            ->get();
    }

    /** @return Collection<int, array{currency: string, count: int, total: float}> */
    public function contractTotalsByCurrency(?User $user = null): Collection
    {
        return $this->contractsByCurrency(
            $this->contracts()->visibleTo($user),
            withPaid: false,
        );
    }

    public function incomeContracts(): HasMany
    {
        return $this->hasMany(Contract::class)
            ->whereHas('contractType', fn (Builder $query) => $query->where('direction', ContractDirection::Income->value));
    }

    /** @return Collection<int, array{currency: string, count: int, total: float, paid: float}> */
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
