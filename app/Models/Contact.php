<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class Contact extends Model
{
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
        'contact_person',
        'director_name',
        'bank_account',
        'bank_name',
        'mfo',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    public const STATUS_INACTIVE = 0;

    public const STATUS_ACTIVE = 1;

    public const TYPE_LEGAL = 'legal';

    public const TYPE_INDIVIDUAL = 'individual';

    public static function getStatuses(): array
    {
        return [
            self::STATUS_ACTIVE => __('app.status.active'),
            self::STATUS_INACTIVE => __('app.status.inactive'),
        ];
    }

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

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
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

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }
}
