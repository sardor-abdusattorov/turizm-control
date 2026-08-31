<?php

namespace App\Services\Contracts;

use App\Models\Contract;
use App\Models\ContractApprover;
use App\Models\Department;
use App\Models\User;

class ApprovalChain
{
    /** @param  array<int, int>  $userIds */
    public function requeue(Contract $contract, array $userIds): int
    {
        $order = 1;

        foreach ($userIds as $userId) {
            ContractApprover::create([
                'contract_id' => $contract->id,
                'user_id' => $userId,
                'order' => $order++,
                'status' => ContractApprover::STATUS_QUEUED,
            ]);
        }

        return $order - 1;
    }

    public function buildFromFlow(Contract $contract): int
    {
        return $this->requeue($contract, $this->flowUserIds());
    }

    /** @param  array<int, int>  $userIds */
    public function resyncOnEdit(Contract $contract, array $userIds, bool $cancelDecided): void
    {
        if ($cancelDecided) {
            $contract->forceFill([
                'status' => Contract::STATUS_DRAFT,
                'signed_at' => null,
            ])->saveQuietly();

            $contract->invalidateAllApprovers('invalidated_on_edit');
        }

        $contract->approvers()
            ->whereIn('status', [ContractApprover::STATUS_QUEUED, ContractApprover::STATUS_PENDING])
            ->update([
                'status' => ContractApprover::STATUS_INVALIDATED,
                'system_comment' => 'invalidated_on_edit',
                'acted_at' => now(),
            ]);

        $this->requeue($contract, $userIds);
    }

    /** @return array<int, int> */
    public function defaultUserIdsFor(User $user): array
    {
        $ids = $user->defaultRecipients()
            ->where('users.status', User::STATUS_ACTIVE)
            ->pluck('users.id')
            ->all();

        if ($ids === []) {
            $ids = $this->flowUserIds();
        }

        return array_map('intval', $ids);
    }

    /** @return array<int, string> */
    public function preview(): array
    {
        $rows = [];
        $position = 1;

        foreach ($this->flowSteps() as $step) {
            $deptName = $step['department']->getTranslation('name', app()->getLocale());
            $rows[] = "{$position}. {$deptName} — {$step['user']->name}";
            $position++;
        }

        return $rows;
    }

    /** @return array<int, array{department: Department, user: User}> */
    private function flowSteps(): array
    {
        $steps = [];
        $seen = [];

        foreach (Department::approvalFlow() as $code) {
            $department = Department::findByCode($code);
            $user = $department?->approverUser();

            if (! $department || ! $user || in_array($user->id, $seen, true)) {
                continue;
            }

            $steps[] = ['department' => $department, 'user' => $user];
            $seen[] = $user->id;
        }

        return $steps;
    }

    /** @return array<int, int> */
    private function flowUserIds(): array
    {
        return array_map(static fn (array $step): int => $step['user']->id, $this->flowSteps());
    }
}
