<?php

namespace App\Enums;

enum PaymentStatus: string
{
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

    /**
     * value => label map for select filters and pickers.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_reduce(
            self::cases(),
            static function (array $carry, self $status): array {
                $carry[$status->value] = $status->label();

                return $carry;
            },
            [],
        );
    }
}
