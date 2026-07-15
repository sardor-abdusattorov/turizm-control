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

    public function participations(): HasMany
    {
        return $this->hasMany(ProjectParticipant::class);
    }

    /**
     * Per-currency totals of this sponsor's project participations: one row
     * per currency with the count, pledged and paid sums, ordered by count.
     * Powers the "projects" badge breakdown on the sponsors list — mixed
     * currencies stay apart, never converted.
     *
     * @return Collection<int, array{currency: string, count: int, total: float, paid: float}>
     */
    public function projectTotalsByCurrency(): Collection
    {
        $rows = $this->participations()
            ->selectRaw('currency_id, COUNT(*) as participations_count, SUM(amount) as total_amount, SUM(paid_amount) as total_paid')
            ->groupBy('currency_id')
            ->get();

        $currencies = Currency::query()
            ->whereIn('id', $rows->pluck('currency_id')->filter())
            ->pluck('short_name', 'id');

        return $rows
            ->map(fn (ProjectParticipant $row): array => [
                'currency' => $currencies->get($row->currency_id) ?? '—',
                'count' => (int) $row->participations_count,
                'total' => (float) $row->total_amount,
                'paid' => (float) $row->total_paid,
            ])
            ->sortByDesc('count')
            ->values();
    }
}
