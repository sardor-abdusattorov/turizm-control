<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum PaymentSubject: string
{
    use HasOptions;

    case Contract = 'contract';
    case Project = 'project';

    public function label(): string
    {
        return __('app.payment.subject.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Contract => 'info',
            self::Project => 'warning',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Contract => 'heroicon-o-document-text',
            self::Project => 'heroicon-o-presentation-chart-bar',
        };
    }
}
