<?php

namespace App\Models;

use App\Enums\ContractDirection;
use App\Enums\CounterpartyKind;
use App\Models\Concerns\HasActiveOptions;
use App\Models\Concerns\HasActiveStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

/**
 * Kind of a contract — the hard nomenclature the registry is built from
 * (space rental, stand construction, services… vs participant fees,
 * sponsorship). Carries the money direction so project income/expense
 * aggregates fall out of the classification for free.
 */
class ContractType extends Model
{
    use HasActiveOptions;
    use HasActiveStatus;
    use HasFactory;
    use HasTranslations;

    public $translatable = ['title', 'description'];

    protected $fillable = [
        'title',
        'description',
        'direction',
        'counterparty_kind',
        'sort',
        'status',
    ];

    protected $casts = [
        'direction' => ContractDirection::class,
        'counterparty_kind' => CounterpartyKind::class,
        'status' => 'boolean',
    ];

    /**
     * Whether contracts of this kind are signed with a {@see Sponsor}
     * (sponsorship) rather than a {@see Contact}.
     */
    public function usesSponsor(): bool
    {
        return $this->counterparty_kind === CounterpartyKind::Sponsor;
    }

    /**
     * Ids of the active contract types that face a Sponsor — used by the
     * contract form to decide which counterparty picker to show.
     *
     * @return array<int, int>
     */
    public static function sponsorFacingIds(): array
    {
        return static::query()
            ->where('counterparty_kind', CounterpartyKind::Sponsor->value)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * Active contract types as id => localized title pairs (for Select::options).
     *
     * @return array<int, string>
     */
    public static function getActive(): array
    {
        return static::activeOptions('title');
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }
}
