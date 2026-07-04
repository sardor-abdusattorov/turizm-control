<?php

namespace App\Rules;

use App\Models\ProjectParticipant;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Fails when the entered installment would take the participant past their
 * pledged fee. The absolute-money analogue of PaymentWithinRemaining.
 */
class ProjectPaymentWithinPledge implements ValidationRule
{
    public function __construct(private ?ProjectParticipant $participant) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $this->participant) {
            return;
        }

        $remaining = $this->participant->remainingAmount();

        if ((float) $value > $remaining + 0.001) {
            $fail(__('app.message.project_payment_exceeds_pledge', [
                'amount' => number_format($remaining, 2, ',', ' '),
            ]));
        }
    }
}
