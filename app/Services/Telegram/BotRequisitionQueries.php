<?php

namespace App\Services\Telegram;

use App\Enums\ApprovalStatus;
use App\Models\Approval;
use App\Models\Requisition;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class BotRequisitionQueries
{
    /** @return Builder<Requisition> */
    public function awaiting(User $user): Builder
    {
        return Requisition::query()
            ->awaiting($user)
            ->orderByDesc('id');
    }

    /** @return Builder<Requisition> */
    public function mine(User $user): Builder
    {
        return Requisition::query()
            ->where('author_id', $user->id)
            ->orderByDesc('id');
    }

    /** @return Builder<Requisition> */
    public function history(User $user): Builder
    {
        return Requisition::query()
            ->whereHas('approvals', fn (Builder $approvals) => $approvals
                ->where('user_id', $user->id)
                ->whereIn('status', [ApprovalStatus::Approved, ApprovalStatus::Rejected]))
            ->orderByDesc(
                Approval::query()
                    ->select('acted_at')
                    ->where('approvable_type', (new Requisition)->getMorphClass())
                    ->whereColumn('approvals.approvable_id', 'requisitions.id')
                    ->where('user_id', $user->id)
                    ->whereIn('status', [ApprovalStatus::Approved, ApprovalStatus::Rejected])
                    ->orderByDesc('acted_at')
                    ->limit(1)
            );
    }
}
