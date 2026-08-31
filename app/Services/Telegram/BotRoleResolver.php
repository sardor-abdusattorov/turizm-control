<?php

namespace App\Services\Telegram;

use App\Enums\ApprovalStatus;
use App\Models\Contract;
use App\Models\ContractApprover;
use App\Models\Requisition;
use App\Models\User;

/**
 * Reads what role-buckets a user falls into for the bot menu. A single user
 * can belong to several at once (a manager who is also a lawyer); the menu
 * just shows whichever buckets are non-empty.
 */
class BotRoleResolver
{
    public function awaitingMyDecisionCount(User $user): int
    {
        return ContractApprover::query()
            ->where('user_id', $user->id)
            ->where('status', ContractApprover::STATUS_PENDING)
            ->count();
    }

    public function myContractsCount(User $user): int
    {
        return Contract::query()->where('responsible_id', $user->id)->count();
    }

    public function pendingDirectorHandoffCount(User $user): int
    {
        return Contract::query()
            ->where('responsible_id', $user->id)
            ->where('status', Contract::STATUS_PENDING_DIRECTOR)
            ->count();
    }

    /**
     * Contracts the user has already acted on as an approver — approved
     * or rejected. Drives the "History of my decisions" bucket in the
     * bot menu.
     */
    public function historyCount(User $user): int
    {
        return Contract::query()
            ->whereHas('approvers', fn ($q) => $q
                ->where('user_id', $user->id)
                ->whereIn('status', [
                    ContractApprover::STATUS_APPROVED,
                    ContractApprover::STATUS_REJECTED,
                ])
            )
            ->count();
    }

    public function canSeeAllContracts(User $user): bool
    {
        return $user->hasRole('super_admin') || $user->can('view_all_contracts');
    }

    public function allContractsCount(User $user): int
    {
        return Contract::query()->visibleTo($user)->count();
    }

    public function requisitionsAwaitingCount(User $user): int
    {
        return Requisition::query()->awaiting($user)->count();
    }

    public function myRequisitionsCount(User $user): int
    {
        return Requisition::query()->where('author_id', $user->id)->count();
    }

    /**
     * Requisitions this approver has already decided on — without this bucket
     * they vanish from the bot the moment the decision is made, since an
     * approver is not their author.
     */
    public function requisitionHistoryCount(User $user): int
    {
        return Requisition::query()
            ->whereHas('approvals', fn ($approvals) => $approvals
                ->where('user_id', $user->id)
                ->whereIn('status', [ApprovalStatus::Approved, ApprovalStatus::Rejected]))
            ->count();
    }

    public function canSeeProjects(User $user): bool
    {
        return $user->hasRole('super_admin') || $user->can('view_any_project');
    }

    public function isLawyer(User $user): bool
    {
        return $user->department?->code === 'legal';
    }

    public function isAccountant(User $user): bool
    {
        return $user->department?->code === 'accounting';
    }

    public function isDirector(User $user): bool
    {
        return $user->hasRole(Contract::DIRECTOR_ROLE);
    }
}
