<?php

declare(strict_types=1);

namespace App\Services\Approvals;

use App\Enums\ApprovalStatus;
use App\Models\Approval;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class ApprovalChain
{
    /**
     * Lay the chain out for the round that is currently running.
     *
     * Voided rows are history and are never touched: once a round is annulled
     * its rows keep the verdicts they carried, and the next round is written
     * as new rows. That is the only way a record can show that somebody
     * rejected it, that it was corrected, and that it went round again.
     *
     * @param  array<int, int>  $userIds
     */
    public function sync(Model $record, array $userIds): void
    {
        $userIds = array_values(array_unique(array_filter($userIds)));

        $live = $record->approvals()->active()->get()->keyBy('user_id');
        $round = $this->currentRound($record, $live);

        $this->dropRemoved($record, $userIds);

        foreach ($this->order($userIds) as $index => $userId) {
            $order = $index + 1;
            $approval = $live->get($userId);

            if ($approval?->status->isFinal()) {
                $approval->update(['order' => $order]);

                continue;
            }

            if ($approval) {
                $approval->update([
                    'order' => $order,
                    'status' => ApprovalStatus::Queued,
                    'comment' => null,
                    'acted_at' => null,
                ]);

                continue;
            }

            $record->approvals()->create([
                'user_id' => $userId,
                'order' => $order,
                'round' => $round,
                'status' => ApprovalStatus::Queued,
            ]);
        }

        $record->unsetRelation('approvals');
    }

    /**
     * Take everybody off the chain who is no longer on it. Somebody whose turn
     * had already opened is voided — the record should show they were asked
     * and then taken off — while somebody still waiting their turn never
     * happened at all and leaves nothing behind. A verdict is never removed.
     *
     * @param  array<int, int>  $userIds
     */
    protected function dropRemoved(Model $record, array $userIds): void
    {
        $record->approvals()
            ->active()
            ->whereNotIn('user_id', $userIds ?: [0])
            ->whereIn('status', [ApprovalStatus::Queued, ApprovalStatus::Pending])
            ->get()
            ->each(function (Approval $approval): void {
                if ($approval->status === ApprovalStatus::Pending) {
                    $approval->invalidate();

                    return;
                }

                $approval->delete();
            });
    }

    /**
     * The round the chain is on: the one the live rows already belong to, or
     * the next one when every previous row has been voided.
     *
     * @param  Collection<int, Approval>  $live
     */
    protected function currentRound(Model $record, Collection $live): int
    {
        if ($live->isNotEmpty()) {
            return (int) $live->max('round');
        }

        return ((int) $record->approvals()->max('round')) + 1;
    }

    /**
     * The chain runs in the order the author picked, the same way the contract
     * chain does — the sequence is a statement of intent, not something to be
     * re-sorted underneath them. Ids that name nobody are dropped.
     *
     * @param  array<int, int>  $userIds
     * @return array<int, int>
     */
    protected function order(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $existing = User::query()->whereKey($userIds)->pluck('id')->all();

        return array_values(array_filter(
            $userIds,
            fn (int $userId): bool => in_array($userId, $existing, true),
        ));
    }

    /**
     * The step whose turn it is — every row sharing the lowest open order, so
     * two people on the same step are asked together.
     *
     * @return Collection<int, Approval>
     */
    public function nextInLine(Model $record): Collection
    {
        $pending = $record->approvals()
            ->active()
            ->whereIn('status', [ApprovalStatus::Queued, ApprovalStatus::Pending])
            ->orderBy('order')
            ->get();

        if ($pending->isEmpty()) {
            return collect();
        }

        return $pending->where('order', $pending->first()->order)->values();
    }

    public function invalidateAll(Model $record): void
    {
        $record->approvals()
            ->active()
            ->get()
            ->each(fn (Approval $approval) => $approval->invalidate());

        $record->unsetRelation('approvals');
    }
}
