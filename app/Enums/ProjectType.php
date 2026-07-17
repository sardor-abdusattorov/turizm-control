<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum ProjectType: string
{
    use HasOptions;

    case Internal = 'internal';
    case International = 'international';

    public function label(): string
    {
        return __('app.project.type.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Internal => 'info',
            self::International => 'success',
        };
    }
}
