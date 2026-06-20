<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Shared active/inactive boolean-status pattern used by every reference
 * model (Department, Position, Currency, Contact, OrderType, Order,
 * ContractTemplate). Owners of this trait still need `'status' => 'boolean'`
 * in their own $casts.
 */
trait HasActiveStatus
{
    public const STATUS_INACTIVE = 0;

    public const STATUS_ACTIVE = 1;

    /** @return array<int, string> value => label */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_ACTIVE => __('app.status.active'),
            self::STATUS_INACTIVE => __('app.status.inactive'),
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }
}
