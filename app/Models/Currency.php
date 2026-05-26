<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class Currency extends Model
{
    use HasTranslations;

    protected $table = 'currencies';

    public $translatable = ['name'];

    protected $fillable = [
        'name',
        'short_name',
        'value',
        'status',
        'sort',
    ];

    protected $casts = [
        'status' => 'boolean',
        'value' => 'decimal:2',
    ];

    const STATUS_INACTIVE = 0;
    const STATUS_ACTIVE = 1;

    /**
     * Get available statuses
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_ACTIVE => __('app.status.active'),
            self::STATUS_INACTIVE => __('app.status.inactive'),
        ];
    }

    /**
     * Scope for active currencies
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public static function getActive(): array
    {
        return static::query()
            ->where('status', 1)
            ->orderBy('sort')
            ->get()
            ->mapWithKeys(fn ($item) => [
                $item->id => $item->getTranslation('name', app()->getLocale()),
            ])
            ->toArray();
    }

}
