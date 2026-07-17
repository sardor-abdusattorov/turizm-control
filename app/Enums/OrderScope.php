<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum OrderScope: string
{
    use HasOptions;

    case Internal = 'internal';
    case External = 'external';

    public function label(): string
    {
        return __('app.order.scope.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Internal => 'info',
            self::External => 'success',
        };
    }
}
