<?php

namespace App\Models;

use App\Enums\ContractDirection;
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
    use HasActiveStatus;
    use HasFactory;
    use HasTranslations;

    public $translatable = ['title', 'description'];

    protected $fillable = [
        'title',
        'description',
        'direction',
        'sort',
        'status',
    ];

    protected $casts = [
        'direction' => ContractDirection::class,
        'status' => 'boolean',
    ];

    /**
     * Active contract types as id => localized title pairs (for Select::options).
     *
     * @return array<int, string>
     */
    public static function getActive(): array
    {
        return static::query()
            ->active()
            ->orderBy('sort')
            ->get()
            ->mapWithKeys(fn (self $item) => [
                $item->id => $item->getTranslation('title', app()->getLocale()),
            ])
            ->toArray();
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    public function contractTemplates(): HasMany
    {
        return $this->hasMany(ContractTemplate::class);
    }
}
