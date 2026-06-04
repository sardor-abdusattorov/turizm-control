<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContractTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_type_id',
        'name',
        'language',
        'template_file',
        'sort',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
        'sort' => 'integer',
    ];

    public const STATUS_INACTIVE = 0;

    public const STATUS_ACTIVE = 1;

    public static function getLanguages(): array
    {
        return [
            'ru' => __('app.label.ru'),
            'uz' => __('app.label.uz'),
            'en' => __('app.label.en'),
        ];
    }

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

    public static function defaultForOrderType(int $orderTypeId, string $language = 'ru'): ?self
    {
        return static::query()
            ->where('order_type_id', $orderTypeId)
            ->where('language', $language)
            ->active()
            ->orderBy('sort')
            ->orderBy('id')
            ->first();
    }

    public function orderType(): BelongsTo
    {
        return $this->belongsTo(OrderType::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }
}
