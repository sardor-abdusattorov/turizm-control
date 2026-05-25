<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class Position extends Model
{
    use HasTranslations;

    public $translatable = ['name'];

    protected $fillable = [
        'name',
        'sort',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
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
     * Departments that have this position
     */
    public function departments()
    {
        return $this->belongsToMany(Department::class, 'department_position');
    }

    /**
     * Scope for active positions
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }
}
