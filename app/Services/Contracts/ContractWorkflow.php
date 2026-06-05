<?php

namespace App\Services\Contracts;

use App\Models\Contract;
use App\Models\ContractApprover;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ContractWorkflow
{
    public function __construct(public ContractNotifier $notifier) {}

    public function submit(Contract $contract, ?User $user = null): bool
    {
        $user ??= auth()->user();

        if (! $contract->canBeSubmittedBy($user)) {
            return false;
        }

        DB::transaction(function () use ($contract): void {
            $contract->update(['status' => Contract::STATUS_IN_REVIEW]);

            $current = $contract->currentApprover();

            if ($current) {
                $this->notifier->notifyApprovalRequested($current);
            }
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

        DB::transaction(function () use ($contract, $current, $comment): void {
            $current->markApproved($comment);

            if ($contract->fresh()->allApproved()) {
                $contract->update([
                    'status' => Contract::STATUS_APPROVED,
                    'signed_at' => now()->toDateString(),
                ]);
                $this->notifier->notifyApproved($contract);

                return;
            }

            $next = $contract->fresh()->currentApprover();

            if ($next) {
                $this->notifier->notifyApprovalRequested($next);
            }
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

        DB::transaction(function () use ($contract, $current, $comment): void {
            $current->markRejected($comment);
            $contract->update(['status' => Contract::STATUS_REJECTED]);
            $this->notifier->notifyRejected($contract, $comment);
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

        DB::transaction(function () use ($contract, $current, $comment): void {
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
        });

        return true;
    }
}
