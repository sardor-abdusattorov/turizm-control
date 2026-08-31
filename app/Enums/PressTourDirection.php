<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum PressTourDirection: string
{
    use HasOptions;

    case Inbound = 'inbound';
    case Local = 'local';
    case Outbound = 'outbound';

    public function label(): string
    {
        return __('app.press_tour.direction.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Inbound => 'success',
            self::Local => 'info',
            self::Outbound => 'warning',
        };
    }
}
