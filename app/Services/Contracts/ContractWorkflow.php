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
            // If there are no active approvers (every row is INVALIDATED
            // because the contract was edited mid-flow), rebuild the
            // queue from the global settings flow before sending.
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

    /**
     * Walk the chain and return the next approver that can actually act —
     * the first row that is QUEUED (not yet started) or PENDING (already
     * reviewing). Skips rows whose user is no longer active, marking them
     * SKIPPED so the chain still reads cleanly. Caller promotes a QUEUED
     * row by invoking startReview() on the returned approver.
     */
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

                return;
            }

            $next = $this->advanceToActiveApprover($contract);

            if (! $next) {
                // Skipping all remaining inactive approvers cleared the
                // queue — treat the contract as fully approved.
                if ($contract->fresh()->allApproved()) {
                    $contract->update([
                        'status' => Contract::STATUS_APPROVED,
                        'signed_at' => now()->toDateString(),
                    ]);
                    $this->notifier->notifyApproved($contract);
                }
            } else {
                $next->startReview($this->slaDays());
                $this->notifier->notifyApprovalRequested($next);
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
            $this->notifier->notifyRejected($contract, $comment);

            $this->logWorkflowEvent(
                event: 'Contract Rejected',
                contract: $contract,
                user: $user,
                properties: ['comment' => $comment],
            );
        });

        return true;
    }

    public function returnForRevision(Contract $contract, User $user, ?string $comment = null): bool
    {
        if (! $contract->canBeApprovedBy($user)) {
            return false;
        }

        $current = $contract->currentApprover();

        if (! $current) {
            return false;
        }

        DB::transaction(function () use ($contract, $current, $comment, $user): void {
            $current->markReturned($comment);

            $contract->approvers()
                ->where('id', '!=', $current->id)
                ->update([
                    'status' => ContractApprover::STATUS_QUEUED,
                    'comment' => null,
                    'acted_at' => null,
                ]);

            $contract->update(['status' => Contract::STATUS_DRAFT]);
            $this->notifier->notifyReturned($contract, $comment);

            $this->logWorkflowEvent(
                event: 'Contract Returned',
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
