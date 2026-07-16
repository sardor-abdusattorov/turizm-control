<?php

namespace App\Console\Commands;

use App\Models\Contract;
use App\Models\ContractApprover;
use App\Services\Contracts\ContractNotifier;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('contracts:send-approval-reminders')]
#[Description('Notify approvers whose review deadline is approaching or overdue')]
class SendApprovalReminders extends Command
{
    public function handle(ContractNotifier $notifier): int
    {
        $candidates = ContractApprover::query()
            ->where('status', ContractApprover::STATUS_PENDING)
            ->whereNotNull('due_at')
            // Both review stages — the director's SLA must be reminded too.
            ->whereHas('contract', fn ($query) => $query->whereIn('status', [
                Contract::STATUS_IN_REVIEW,
                Contract::STATUS_IN_REVIEW_DIRECTOR,
            ]))
            ->with(['contract', 'user'])
            ->get();

        $sent = 0;

        foreach ($candidates as $approver) {
            if (! $approver->contract?->isCurrentApprover($approver->user)) {
                continue;
            }

            if (! $approver->needsReminder()) {
                continue;
            }

            $notifier->notifyReminder($approver);
            $approver->markReminded();
            $sent++;
        }

        $this->info("Approval reminders sent: {$sent}");

        return self::SUCCESS;
    }
}
