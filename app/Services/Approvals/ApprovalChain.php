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
    /** @param  array<int, int>  $userIds */
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

    /** @param  array<int, int>  $userIds */
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

    /** @param  Collection<int, Approval>  $live */
    protected function currentRound(Model $record, Collection $live): int
    {
        if ($live->isNotEmpty()) {
            return (int) $live->max('round');
        }

        return ((int) $record->approvals()->max('round')) + 1;
    }

    /**
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

    /** @return Collection<int, Approval> */
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
