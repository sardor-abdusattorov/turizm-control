<?php

namespace App\Models;

use App\Models\Concerns\HasActiveStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sponsor extends Model
{
    use HasActiveStatus;
    use HasFactory;

    protected $fillable = [
        'name',
        'inn',
        'contact_person',
        'phone',
        'email',
        'website',
        'address',
        'description',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    /**
     * @return array<int, string>
     */
    public static function getActive(): array
    {
        return static::query()
            ->active()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public function participations(): HasMany
    {
        return $this->hasMany(ProjectParticipant::class);
    }
}
