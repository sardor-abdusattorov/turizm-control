<?php

namespace App\Console\Commands;

use App\Models\Contract;
use App\Services\Payments\PaymentNotifier;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('contracts:send-payment-reminders')]
#[Description('Remind responsible managers about approved contracts that are still not fully paid')]
class SendPaymentReminders extends Command
{
    /** The first nudge lands a week after approval… */
    private const FIRST_AFTER_DAYS = 7;

    /** …and repeats weekly until the contract is fully paid. */
    private const REPEAT_EVERY_DAYS = 7;

    public function handle(PaymentNotifier $notifier): int
    {
        $sent = 0;

        // acceptingPayments() = approved and not fully paid; signed_at is
        // stamped by the workflow the moment the chain completes, so it is
        // the "approved N days ago" anchor. The weekly cadence needs no
        // bookkeeping column: the command runs once a day, and day-since
        // counts hit a multiple of 7 exactly once each week.
        Contract::query()
            ->acceptingPayments()
            ->whereNotNull('signed_at')
            ->whereDate('signed_at', '<=', today()->subDays(self::FIRST_AFTER_DAYS))
            ->with(['responsible.telegram'])
            ->get()
            ->each(function (Contract $contract) use ($notifier, &$sent): void {
                $days = (int) $contract->signed_at->startOfDay()->diffInDays(today());

                if ($days % self::REPEAT_EVERY_DAYS !== 0) {
                    return;
                }

                $notifier->notifyPaymentOverdue($contract, $days);
                $sent++;
            });

        $this->info("Payment reminders sent: {$sent}");

        return self::SUCCESS;
    }
}
