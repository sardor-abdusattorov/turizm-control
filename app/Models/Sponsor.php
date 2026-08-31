<?php

namespace App\Models;

use App\Models\Concerns\HasActiveStatus;
use App\Models\Concerns\SearchesByTaxId;
use App\Models\Concerns\SumsContractsByCurrency;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Sponsor extends Model
{
    use HasActiveStatus;
    use HasFactory;
    use SearchesByTaxId;
    use SumsContractsByCurrency;

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

    /** @return array<int, string> */
    public static function getActive(): array
    {
        return static::query()
            ->active()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public function sponsorshipContracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    /** @return Collection<int, array{currency: string, count: int, total: float, paid: float}> */
    public function projectTotalsByCurrency(?User $user = null): Collection
    {
        return $this->contractsByCurrency(
            $this->sponsorshipContracts()
                ->visibleTo($user)
                ->where('status', '!=', Contract::STATUS_REJECTED->value),
            withPaid: true,
        );
    }
}
