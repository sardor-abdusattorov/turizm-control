<?php

namespace App\Models;

use App\Models\Concerns\HasActiveStatus;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class Position extends Model
{
    use HasActiveStatus;
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

    /**
     * Departments that have this position
     */
    public function departments()
    {
        return $this->belongsToMany(Department::class, 'department_position');
    }

    /**
     * Active positions as id => localized name pairs (for Select::options).
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
                $item->id => $item->getTranslation('name', app()->getLocale()),
            ])
            ->toArray();
    }
}
