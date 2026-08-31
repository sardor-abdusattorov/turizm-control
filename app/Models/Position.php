<?php

namespace App\Models;

use App\Models\Concerns\HasActiveOptions;
use App\Models\Concerns\HasActiveStatus;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class Position extends Model
{
    use HasActiveOptions;
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

    public function departments()
    {
        return $this->belongsToMany(Department::class, 'department_position');
    }

    /** @return array<int, string> */
    public static function getActive(): array
    {
        return static::activeOptions('name');
    }
}
