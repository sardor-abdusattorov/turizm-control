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

    public function usesSponsor(): bool
    {
        return $this->counterparty_kind === CounterpartyKind::Sponsor;
    }

    /** @return array<int, int> */
    public static function sponsorFacingIds(): array
    {
        return static::query()
            ->where('counterparty_kind', CounterpartyKind::Sponsor->value)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /** @return array<int, string> */
    public static function getActive(): array
    {
        return static::activeOptions('title');
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }
}
