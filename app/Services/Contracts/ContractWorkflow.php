<?php

namespace App\Services\Contracts;

use App\Models\Contract;
use App\Models\ContractApprover;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use MrAdder\FilamentLogger\Facades\FilamentLogger;

class ContractWorkflow
{
    public function __construct(public ContractNotifier $notifier) {}

    public function submit(Contract $contract, ?User $user = null): bool
    {
        $user ??= auth()->user();

        if (! $contract->canBeSubmittedBy($user)) {
            return false;
        }

        DB::transaction(function () use ($contract, $user): void {
            if (! $contract->activeApprovers()->exists()) {
                $contract->buildApprovalChainFromFlow();
            }

            $contract->update(['status' => Contract::STATUS_IN_REVIEW]);

            $current = $this->advanceToActiveApprover($contract);

            if ($current) {
                $current->startReview($this->slaDays());
                $this->notifier->notifyApprovalRequested($current);
            }

            $this->logWorkflowEvent(
                event: 'Contract Submitted',
                contract: $contract,
                user: $user,
                properties: ['next_approver_id' => $current?->user_id],
            );
        });

        return true;
    }

    private function advanceToActiveApprover(Contract $contract): ?ContractApprover
    {
        while ($next = $contract->fresh()->nextInLineApprover()) {
            if ($next->user && (bool) $next->user->status) {
                return $next;
            }

            $next->update([
                'status' => ContractApprover::STATUS_SKIPPED,
                'comment' => __('app.message.approver_inactive'),
                'acted_at' => now(),
            ]);
        }

        return null;
    }

    public function approve(Contract $contract, User $user, ?string $comment = null): bool
    {
        if (! $contract->canBeApprovedBy($user)) {
            return false;
        }

        $current = $contract->currentApprover();

        if (! $current) {
            return false;
        }

        DB::transaction(function () use ($contract, $current, $comment, $user): void {
            $current->markApproved($comment);

            if ($contract->fresh()->allApproved()) {
                $this->finalizeOrAwaitDirector($contract, $user, $comment, $current);

                return;
            }

            $next = $this->advanceToActiveApprover($contract);

            if (! $next && $contract->fresh()->allApproved()) {
                $this->finalizeOrAwaitDirector($contract, $user, $comment, $current);

                return;
            }

            if ($next) {
                $next->startReview($this->slaDays());
                $this->notifier->notifyApprovalRequested($next);
                // Keep the manager in the loop on every step, not just the
                // final sign-off: "approved by the lawyer, now with the
                // accountant".
                $this->notifier->notifyStepApproved($contract, $current);
            }

            $this->logWorkflowEvent(
                event: 'Contract Step Approved',
                contract: $contract,
                user: $user,
                properties: [
                    'comment' => $comment,
                    'next_approver_id' => $next?->user_id,
                ],
            );
        });

        return true;
    }

    private function finalizeOrAwaitDirector(Contract $contract, User $user, ?string $comment, ContractApprover $current): void
    {
        $fresh = $contract->fresh();

        if ($fresh->directorUser() && ! $fresh->hasDirectorApprover()) {
            $contract->update(['status' => Contract::STATUS_PENDING_DIRECTOR]);

            // Legal + accounting are done — nudge the manager that it's
            // their turn to hand the contract to the director.
            $this->notifier->notifyStepApproved($contract, $current);

            $this->logWorkflowEvent(
                event: 'Contract Awaiting Director',
                contract: $contract,
                user: $user,
                properties: ['comment' => $comment],
            );

            return;
        }

        $contract->update([
            'status' => Contract::STATUS_APPROVED,
            'signed_at' => now()->toDateString(),
        ]);
        $this->notifier->notifyApproved($contract);

        $this->logWorkflowEvent(
            event: 'Contract Approved',
            contract: $contract,
            user: $user,
            properties: ['comment' => $comment, 'final' => true],
        );
    }

    public function submitToDirector(Contract $contract, ?User $user = null): bool
    {
        $user ??= auth()->user();

        if (! $contract->canBeSentToDirectorBy($user)) {
            return false;
        }

        DB::transaction(function () use ($contract, $user): void {
            $director = $contract->appendDirectorApprover();

            if (! $director) {
                return;
            }

            $contract->update(['status' => Contract::STATUS_IN_REVIEW_DIRECTOR]);
            $director->startReview($this->slaDays());
            $this->notifier->notifyApprovalRequested($director);

            $this->logWorkflowEvent(
                event: 'Contract Sent To Director',
                contract: $contract,
                user: $user,
                properties: ['director_id' => $director->user_id],
            );
        });

        return true;
    }

    public function reject(Contract $contract, User $user, ?string $comment = null): bool
    {
        if (! $contract->canBeApprovedBy($user)) {
            return false;
        }

        $current = $contract->currentApprover();

        if (! $current) {
            return false;
        }

        DB::transaction(function () use ($contract, $current, $comment, $user): void {
            $current->markRejected($comment);
            $contract->update(['status' => Contract::STATUS_REJECTED]);
            $this->notifier->notifyRejected($contract, $comment, $current);

            $this->logWorkflowEvent(
                event: 'Contract Rejected',
                contract: $contract,
                user: $user,
                properties: ['comment' => $comment],
            );
        });

        return true;
    }

    private function slaDays(): int
    {
        $days = (int) settings('approval.sla_days', 2);

        return $days > 0 ? $days : 2;
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function logWorkflowEvent(string $event, Contract $contract, ?User $user, array $properties = []): void
    {
        FilamentLogger::log(
            event: $event,
            description: $event.' — '.$contract->number,
            options: [
                'logName' => 'Workflow',
                'subject' => $contract,
                'causer' => $user,
                'properties' => array_filter(
                    array_merge(['contract_number' => $contract->number], $properties),
                    static fn (mixed $value): bool => $value !== null,
                ),
            ],
        );
    }
}
