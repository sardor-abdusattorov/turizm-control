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
            $contract->update(['status' => Contract::STATUS_IN_REVIEW]);

            $current = $contract->currentApprover();

            if ($current) {
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

            $next = $contract->fresh()->currentApprover();

            if ($next) {
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
                    'status' => ContractApprover::STATUS_PENDING,
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
