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
                $this->finalizeOrAwaitDirector($contract, $user, $comment);

                return;
            }

            $next = $this->advanceToActiveApprover($contract);

            if (! $next && $contract->fresh()->allApproved()) {
                // Skipping all remaining inactive approvers cleared the queue.
                $this->finalizeOrAwaitDirector($contract, $user, $comment);

                return;
            }

            if ($next) {
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

    /**
     * The lawyer + accountant stage is done. If a director is configured and
     * hasn't acted yet, park the contract in PENDING_DIRECTOR so the manager
     * can hand it over manually (see submitToDirector). Otherwise — no director
     * configured, or the director just gave the final approval — finalize.
     */
    private function finalizeOrAwaitDirector(Contract $contract, User $user, ?string $comment): void
    {
        $fresh = $contract->fresh();

        if ($fresh->directorUser() && ! $fresh->hasDirectorApprover()) {
            $contract->update(['status' => Contract::STATUS_PENDING_DIRECTOR]);

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

    /**
     * Hand a PENDING_DIRECTOR contract to the role-based director for the final
     * sign-off — appends them as the last approver, flips back to IN_REVIEW and
     * starts their review clock.
     */
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

            $contract->update(['status' => Contract::STATUS_IN_REVIEW]);
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
