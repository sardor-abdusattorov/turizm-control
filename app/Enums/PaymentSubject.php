<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * What a payment settles. A contract payment is a share of that contract's
 * total; a project payment is money spent on a project that never went through
 * a contract, so it carries its own sum.
 */
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
