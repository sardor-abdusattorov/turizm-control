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

    public static function approvalEnabled(): bool
    {
        return (bool) settings('approval.enabled', true);
    }

    private function lockAndRefresh(Contract $contract): bool
    {
        if (! Contract::query()->lockForUpdate()->find($contract->getKey())) {
            return false;
        }

        $contract->refresh();

        return true;
    }

    public function submit(Contract $contract, ?User $user = null): bool
    {
        $user ??= auth()->user();

        DB::beginTransaction();

        try {
            if (! $this->lockAndRefresh($contract) || ! $contract->canBeSubmittedBy($user)) {
                DB::rollBack();

                return false;
            }

            if (! $contract->activeApprovers()->exists()) {
                $contract->buildApprovalChainFromFlow();
            }

            $current = $this->advanceToActiveApprover($contract);

            if (! $current) {
                DB::rollBack();

                return false;
            }

            $contract->update(['status' => Contract::STATUS_IN_REVIEW]);
            $current->startReview($this->slaDays());
            $this->notifier->notifyApprovalRequested($current);

            $this->logWorkflowEvent(
                event: 'Contract Submitted',
                contract: $contract,
                user: $user,
                properties: ['next_approver_id' => $current->user_id],
            );

            DB::commit();

            return true;
        } catch (\Throwable $e) {
            DB::rollBack();

            throw $e;
        }
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
        return (bool) DB::transaction(function () use ($contract, $user, $comment): bool {
            if (! $this->lockAndRefresh($contract) || ! $contract->canBeApprovedBy($user)) {
                return false;
            }

            $current = $contract->currentApprover();

            if (! $current) {
                return false;
            }

            $current->markApproved($comment);

            if ($contract->fresh()->allApproved()) {
                $this->finalizeOrAwaitDirector($contract, $user, $comment, $current);

                return true;
            }

            $next = $this->advanceToActiveApprover($contract);

            if (! $next && $contract->fresh()->allApproved()) {
                $this->finalizeOrAwaitDirector($contract, $user, $comment, $current);

                return true;
            }

            if ($next) {
                $next->startReview($this->slaDays());
                $this->notifier->notifyApprovalRequested($next);

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

            return true;
        });
    }

    private function finalizeOrAwaitDirector(Contract $contract, User $user, ?string $comment, ContractApprover $current): void
    {
        $fresh = $contract->fresh();

        if ($fresh->directorUser() && ! $fresh->hasDirectorApprover()) {
            $contract->update(['status' => Contract::STATUS_PENDING_DIRECTOR]);

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
        $this->notifier->notifyApproved($contract, $current);

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

        return (bool) DB::transaction(function () use ($contract, $user): bool {
            if (! $this->lockAndRefresh($contract) || ! $contract->canBeSentToDirectorBy($user)) {
                return false;
            }

            $director = $contract->appendDirectorApprover();

            if (! $director) {
                return false;
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

            return true;
        });
    }

    public function reject(Contract $contract, User $user, ?string $comment = null): bool
    {
        return (bool) DB::transaction(function () use ($contract, $user, $comment): bool {
            if (! $this->lockAndRefresh($contract) || ! $contract->canBeApprovedBy($user)) {
                return false;
            }

            $current = $contract->currentApprover();

            if (! $current) {
                return false;
            }

            $current->markRejected($comment);
            $contract->update(['status' => Contract::STATUS_REJECTED]);
            $this->notifier->notifyRejected($contract, $comment, $current);

            $this->logWorkflowEvent(
                event: 'Contract Rejected',
                contract: $contract,
                user: $user,
                properties: ['comment' => $comment],
            );

            return true;
        });
    }

    public function returnToWork(Contract $contract, ?User $user = null): bool
    {
        $user ??= auth()->user();

        return (bool) DB::transaction(function () use ($contract, $user): bool {
            if (! $this->lockAndRefresh($contract)) {
                return false;
            }

            if ($contract->status !== Contract::STATUS_REJECTED || ! $contract->canBeEditedBy($user)) {
                return false;
            }

            $previousUserIds = $contract->activeApprovers()
                ->orderBy('order')
                ->pluck('user_id')
                ->all();

            app(ApprovalChain::class)->resyncOnEdit($contract, $previousUserIds, cancelDecided: true);

            $this->logWorkflowEvent(
                event: 'Contract Returned To Work',
                contract: $contract,
                user: $user,
            );

            return true;
        });
    }

    public function reassignCurrentApprover(Contract $contract, User $newApprover, ?User $actor = null): bool
    {
        $actor ??= auth()->user();

        return (bool) DB::transaction(function () use ($contract, $newApprover, $actor): bool {
            if (! $this->lockAndRefresh($contract)) {
                return false;
            }

            $inReview = in_array($contract->status, [
                Contract::STATUS_IN_REVIEW,
                Contract::STATUS_IN_REVIEW_DIRECTOR,
            ], true);

            $current = $contract->currentApprover();

            if (! $inReview || ! $current || $current->user_id === $newApprover->id) {
                return false;
            }

            $current->update([
                'status' => ContractApprover::STATUS_SKIPPED,
                'comment' => __('app.message.approver_reassigned'),
                'acted_at' => now(),
            ]);

            $replacement = ContractApprover::create([
                'contract_id' => $contract->id,
                'user_id' => $newApprover->id,
                'order' => $current->order,
                'status' => ContractApprover::STATUS_PENDING,
            ]);

            $replacement->startReview($this->slaDays());
            $this->notifier->notifyApprovalRequested($replacement);

            $this->logWorkflowEvent(
                event: 'Contract Approver Reassigned',
                contract: $contract,
                user: $actor,
                properties: [
                    'from_user_id' => $current->user_id,
                    'to_user_id' => $newApprover->id,
                ],
            );

            return true;
        });
    }

    private function slaDays(): int
    {
        $days = (int) settings('approval.sla_days', 2);

        return $days > 0 ? $days : 2;
    }

    /** @param  array<string, mixed>  $properties */
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
