<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    protected $fillable = [
        'order_type_id',
        'title',
        'description',
        'file_path',
        'deadline_at',
        'status',
        'created_by',
    ];

    protected $casts = [
        'status' => 'boolean',
        'deadline_at' => 'date',
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

    public function orderType(): BelongsTo
    {
        return $this->belongsTo(OrderType::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
