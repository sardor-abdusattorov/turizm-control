<?php

namespace App\Services\Requisitions;

use App\Enums\RequisitionStatus;
use App\Models\Requisition;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RequisitionWorkflow
{
    /**
     * Hand the requisition to its reviewer and start the clock. The deadline
     * is stamped here from the setting so a later change to the setting never
     * moves a deadline someone is already working to.
     */
    public function submit(Requisition $requisition, ?User $user = null): bool
    {
        return $this->transition($requisition, fn (Requisition $fresh): bool => $fresh->canBeSubmittedBy($user), [
            'status' => RequisitionStatus::InReview,
            'submitted_at' => now(),
            'due_at' => now()->addDays(Requisition::reviewDays()),
            'reviewed_at' => null,
            'review_comment' => null,
        ]);
    }

    public function approve(Requisition $requisition, ?string $comment = null, ?User $user = null): bool
    {
        return $this->transition($requisition, fn (Requisition $fresh): bool => $fresh->canBeReviewedBy($user), [
            'status' => RequisitionStatus::Approved,
            'reviewed_at' => now(),
            'review_comment' => $comment,
        ]);
    }

    public function reject(Requisition $requisition, string $comment, ?User $user = null): bool
    {
        return $this->transition($requisition, fn (Requisition $fresh): bool => $fresh->canBeReviewedBy($user), [
            'status' => RequisitionStatus::Rejected,
            'reviewed_at' => now(),
            'review_comment' => $comment,
        ]);
    }

    /**
     * Re-read the row under a lock before checking its gate: the same
     * requisition can be acted on from two tabs, and the second action must
     * see the first one's result rather than the state it was rendered from.
     *
     * @param  callable(Requisition): bool  $gate
     * @param  array<string, mixed>  $attributes
     */
    private function transition(Requisition $requisition, callable $gate, array $attributes): bool
    {
        return DB::transaction(function () use ($requisition, $gate, $attributes): bool {
            if (! Requisition::query()->lockForUpdate()->find($requisition->getKey())) {
                return false;
            }

            $requisition->refresh();

            if (! $gate($requisition)) {
                return false;
            }

            $requisition->forceFill($attributes)->save();

            return true;
        });
    }
}
