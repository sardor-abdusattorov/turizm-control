<?php

namespace App\Rules;

use App\Models\Contract;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

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
