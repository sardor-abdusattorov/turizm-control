<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Where a tour stands against its plan. The registry is written a year ahead,
 * so a tour lives as a plan first and only later becomes a fact — and the
 * report documents are expected once it has been held.
 */
enum PressTourState: string
{
    use HasOptions;

    case Planned = 'planned';
    case Held = 'held';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __('app.press_tour.state.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Planned => 'gray',
            self::Held => 'success',
            self::Cancelled => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Planned => 'heroicon-o-clock',
            self::Held => 'heroicon-o-check-circle',
            self::Cancelled => 'heroicon-o-x-circle',
        };
    }
}
