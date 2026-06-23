<?php

namespace App\Rules;

use App\Models\Contract;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Fails when the entered percent would take the contract past 100% paid.
 * Shared by the contract "record payment" action and the standalone payment
 * form, so the rule lives in one place (and is reusable from the bot / API).
 */
class PaymentWithinRemaining implements ValidationRule
{
    public function __construct(private ?Contract $contract) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $this->contract) {
            return;
        }

        $remaining = $this->contract->remainingPercent();

        if ((float) $value > $remaining + 0.001) {
            $fail(__('app.message.payment_exceeds_remaining', [
                'percent' => format_percent($remaining),
            ]));
        }
    }
}
