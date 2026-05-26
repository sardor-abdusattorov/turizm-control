<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class OrderType extends Model
{
    use HasTranslations;

    public $translatable = ['title', 'description'];

    protected $fillable = [
        'title',
        'description',
        'sort',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    public const STATUS_INACTIVE = 0;

    public const STATUS_ACTIVE = 1;

    public static function getStatuses(): array
    {
        return [
            self::STATUS_ACTIVE => __('app.status.active'),
            self::STATUS_INACTIVE => __('app.status.inactive'),
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Active order types as id => localized title pairs (for Select::options).
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

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
