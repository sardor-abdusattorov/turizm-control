<?php

namespace App\Services\Telegram;

use App\Models\Contract;
use App\Models\ContractApprover;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class BotContractQueries
{
    /** @return Builder<Contract> */
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

    /** @return Builder<Contract> */
    public function all(User $user): Builder
    {
        return Contract::query()->visibleTo($user)->orderByDesc('id');
    }

    /** @return Builder<Contract> */
    public function awaiting(User $user): Builder
    {
        return Contract::query()
            ->whereHas('approvers', fn ($q) => $q
                ->where('user_id', $user->id)
                ->where('status', ContractApprover::STATUS_PENDING)
            )
            ->orderByDesc('id');
    }

    /** @return Builder<Contract> */
    public function mine(User $user): Builder
    {
        return Contract::query()
            ->where('responsible_id', $user->id)
            ->orderByDesc('id');
    }
}
