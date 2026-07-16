<?php

namespace App\Models;

use App\Models\Concerns\HasActiveStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

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

    /**
     * This sponsor's sponsorship contracts — «Спонсорство» income deals point
     * at a Sponsor through contracts.sponsor_id (only sponsorship contracts
     * ever set it, so the FK alone is the filter). Replaces the old manual
     * project participations.
     */
    public function sponsorshipContracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    /**
     * Per-currency totals of this sponsor's sponsorship contracts the given
     * user may see (defaults to the current one): one row per currency with the
     * count, pledged and paid sums, ordered by count. Rejected contracts are
     * dropped and "paid" is derived from the paid percent. Powers the "projects"
     * badge breakdown on the sponsors list — mixed currencies stay apart.
     *
     * @return Collection<int, array{currency: string, count: int, total: float, paid: float}>
     */
    public function projectTotalsByCurrency(?User $user = null): Collection
    {
        $rows = $this->sponsorshipContracts()
            ->visibleTo($user)
            ->where('status', '!=', Contract::STATUS_REJECTED->value)
            ->selectRaw('currency_id, COUNT(*) as contracts_count, SUM(amount) as total_amount, SUM(amount * paid_percent / 100) as total_paid')
            ->groupBy('currency_id')
            ->get();

        $currencies = Currency::query()
            ->whereIn('id', $rows->pluck('currency_id')->filter())
            ->pluck('short_name', 'id');

        return $rows
            ->map(fn (Contract $row): array => [
                'currency' => $currencies->get($row->currency_id) ?? '—',
                'count' => (int) $row->contracts_count,
                'total' => (float) $row->total_amount,
                'paid' => (float) $row->total_paid,
            ])
            ->sortByDesc('count')
            ->values();
    }
}
