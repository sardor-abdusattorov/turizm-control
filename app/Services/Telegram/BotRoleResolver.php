<?php

namespace App\Services\Telegram;

use App\Models\Contract;
use App\Models\ContractApprover;
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
