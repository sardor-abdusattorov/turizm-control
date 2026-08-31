<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum OrderScope: string
{
    use HasOptions;

    case Committee = 'committee';
    case PrCenter = 'pr_center';

    public function label(): string
    {
        return __('app.order.scope.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Committee => 'success',
            self::PrCenter => 'info',
        };
    }
}
