<?php

namespace App\Services\Telegram;

use App\Models\Contract;
use App\Models\ContractApprover;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * The contract list queries behind the bot menus. Kept out of BotMenuBuilder
 * so that class can stay pure presentation, as its docblock promises.
 */
class BotContractQueries
{
    /**
     * Contracts the user has already decided on (approved or rejected),
     * ordered by their action time so the most recent verdict is on top.
     *
     * @return Builder<Contract>
     */
    public function history(User $user): Builder
    {
        return Contract::query()
            ->whereHas('approvers', fn ($q) => $q
                ->where('user_id', $user->id)
                ->whereIn('status', [
                    ContractApprover::STATUS_APPROVED,
                    ContractApprover::STATUS_REJECTED,
                ])
            )
            ->orderByDesc(
                ContractApprover::query()
                    ->select('acted_at')
                    ->whereColumn('contract_approvers.contract_id', 'contracts.id')
                    ->where('user_id', $user->id)
                    ->whereIn('status', [
                        ContractApprover::STATUS_APPROVED,
                        ContractApprover::STATUS_REJECTED,
                    ])
                    ->orderByDesc('acted_at')
                    ->limit(1)
            );
    }

    /**
     * Every contract system-wide — only oversight roles reach this list.
     *
     * @return Builder<Contract>
     */
    public function all(): Builder
    {
        return Contract::query()->orderByDesc('id');
    }

    /**
     * Contracts awaiting this user's decision right now.
     *
     * @return Builder<Contract>
     */
    public function awaiting(User $user): Builder
    {
        return Contract::query()
            ->whereHas('approvers', fn ($q) => $q
                ->where('user_id', $user->id)
                ->where('status', ContractApprover::STATUS_PENDING)
            )
            ->orderByDesc('id');
    }

    /**
     * The user's own contracts.
     *
     * @return Builder<Contract>
     */
    public function mine(User $user): Builder
    {
        return Contract::query()
            ->where('responsible_id', $user->id)
            ->orderByDesc('id');
    }
}
