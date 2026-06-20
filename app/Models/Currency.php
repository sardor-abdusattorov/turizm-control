<?php

namespace App\Models;

use App\Models\Concerns\HasActiveStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class Currency extends Model
{
    use HasActiveStatus;
    use HasFactory;
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
