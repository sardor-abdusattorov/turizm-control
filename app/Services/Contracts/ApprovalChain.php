<?php

namespace App\Services\Contracts;

use App\Models\Contract;
use App\Models\ContractApprover;
use App\Models\Department;
use App\Models\User;

/**
 * Builds and rebuilds a contract's approver queue. Owns the ways a chain comes
 * to exist — an explicit ordered list of users (manual pick / resubmit) or the
 * department approval flow — plus the read-only preview the create form shows.
 */
class ApprovalChain
{
    /**
     * Queue an ordered list of users as fresh approvers (status QUEUED,
     * order 1..n). Returns the number created.
     *
     * @param  array<int, int>  $userIds
     */
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

    /**
     * Build the chain from the department approval flow (legal → accounting → …),
     * skipping departments with no active approver and de-duplicating a person
     * who heads more than one department.
     */
    public function buildFromFlow(Contract $contract): int
    {
        return $this->requeue($contract, $this->flowUserIds());
    }

    /**
     * Preview of the chain the flow would produce, as "Department — User" lines
     * for the create form.
     *
     * @return array<int, string>
     */
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

    /**
     * Resolved, de-duplicated flow steps: one (department, approver) pair per
     * position in the configured flow.
     *
     * @return array<int, array{department: Department, user: User}>
     */
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

    /**
     * @return array<int, int>
     */
    private function flowUserIds(): array
    {
        return array_map(static fn (array $step): int => $step['user']->id, $this->flowSteps());
    }
}
