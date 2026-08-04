<?php

namespace App\Models;

use App\Enums\PressTourDirection;
use App\Models\Concerns\HasActiveStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A press, blogger or info tour from the «Список пресс, блогер и инфо-туров»
 * registry: foreign media hosted in Uzbekistan, domestic tours around the
 * regions, and local media sent abroad. Unlike an exhibition project a tour
 * carries no money of its own — it rests on a buyruq and is measured by who
 * travelled, where and when.
 */
class PressTour extends Model
{
    use HasActiveStatus;
    use HasFactory;

    protected $fillable = [
        'direction',
        'name',
        'place',
        'period',
        'starts_month',
        'people_count',
        'people_note',
        'responsible',
        'curator',
        'foreign_partner',
        'order_id',
        'notes',
        'status',
        'created_by',
    ];

    protected $casts = [
        'direction' => PressTourDirection::class,
        'starts_month' => 'integer',
        'people_count' => 'integer',
        'status' => 'boolean',
    ];

    /**
     * The buyruq the tour rests on — the 2026 domestic tours all cite
     * приказ № 49-АФ, the same way a project names its basis.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeDirection(Builder $query, PressTourDirection $direction): Builder
    {
        return $query->where('direction', $direction->value);
    }

    /**
     * Headcount as the registry phrased it: the raw note wins when it carried
     * something a plain number cannot say («6+11» — two groups), otherwise the
     * parsed count, and «—» when the registry said n/a.
     */
    public function peopleLabel(): string
    {
        return $this->people_note
            ?: ($this->people_count !== null ? (string) $this->people_count : '—');
    }

    /**
     * Everyone accountable for the tour, owner first, curator second — the two
     * names the registry crammed into a single «Ответственное лицо» cell.
     *
     * @return list<string>
     */
    public function responsibleNames(): array
    {
        return array_values(array_filter([$this->responsible, $this->curator]));
    }

    /**
     * Month names for the picker and the table filter, January first.
     *
     * @return array<int, string>
     */
    public static function monthOptions(): array
    {
        $months = [];

        foreach (range(1, 12) as $month) {
            $months[$month] = ucfirst(now()->startOfYear()->month($month)->translatedFormat('F'));
        }

        return $months;
    }
}
