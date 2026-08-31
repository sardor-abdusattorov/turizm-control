<?php

namespace App\Models;

use App\Enums\PressTourDirection;
use App\Enums\PressTourState;
use App\Models\Concerns\HasActiveStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'state',
        'held_on',
        'order_id',
        'notes',
        'status',
        'created_by',
    ];

    protected $casts = [
        'direction' => PressTourDirection::class,
        'state' => PressTourState::class,
        'held_on' => 'date',
        'starts_month' => 'integer',
        'people_count' => 'integer',
        'status' => 'boolean',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(PressTourAttachment::class)->orderBy('sort')->orderBy('id');
    }

    public function isHeld(): bool
    {
        return $this->state === PressTourState::Held;
    }

    public function awaitsDocuments(): bool
    {
        return $this->isHeld() && $this->attachments()->count() === 0;
    }

    public function peopleLabel(): string
    {
        return $this->people_note
            ?: ($this->people_count !== null ? (string) $this->people_count : '—');
    }

    /** @return list<string> */
    public function responsibleNames(): array
    {
        return array_values(array_filter([$this->responsible, $this->curator]));
    }

    /** @return array<int, string> */
    public static function monthOptions(): array
    {
        $months = [];

        foreach (range(1, 12) as $month) {
            $months[$month] = ucfirst(now()->startOfYear()->month($month)->translatedFormat('F'));
        }

        return $months;
    }
}
