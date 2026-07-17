<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum PaymentStatus: string
{
    use HasOptions;

    case NotPaid = 'not_paid';
    case PartiallyPaid = 'partially_paid';
    case FullyPaid = 'fully_paid';

    public function label(): string
    {
        return __('app.payment_status.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::NotPaid => 'gray',
            self::PartiallyPaid => 'warning',
            self::FullyPaid => 'success',
        };
    }

    public static function fromPercent(float $percent): self
    {
        if ($percent <= 0) {
            return self::NotPaid;
        }

        if ($percent >= 100) {
            return self::FullyPaid;
        }

        return self::PartiallyPaid;
    }
}
