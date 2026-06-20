<?php

namespace App\Enums;

/**
 * Aggregate payment progress on a contract. Stored on the contracts table
 * (denormalised) so the contracts list can filter on it cheaply; recomputed
 * by PaymentObserver whenever a payment is created, updated or deleted.
 */
enum PaymentStatus: string
{
    case NotPaid = 'not_paid';
    case PartiallyPaid = 'partially_paid';
    case FullyPaid = 'fully_paid';

    public function label(): string
    {
        return __('app.payment_status.'.$this->value);
    }

    /** Filament colour token used for the status pill. */
    public function color(): string
    {
        return match ($this) {
            self::NotPaid => 'gray',
            self::PartiallyPaid => 'warning',
            self::FullyPaid => 'success',
        };
    }

    /**
     * Resolve the status for a given paid percentage. Anything ≥ 100 is
     * considered fully paid so rounding noise on the last instalment doesn't
     * leave the contract stuck at "partially paid".
     */
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
