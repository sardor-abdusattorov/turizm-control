<?php

namespace App\Models\Concerns;

use App\Enums\ApprovalStatus;
use App\Models\Approval;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;

/**
 * @property-read Collection<int, Approval> $approvals
 */
trait HasApprovals
{
    public function approvals(): MorphMany
    {
        return $this->morphMany(Approval::class, 'approvable')->orderBy('order')->orderBy('id');
    }

    /**
     * @return Collection<int, Approval>
     */
    public function activeApprovals(): Collection
    {
        return $this->approvals->reject(
            fn (Approval $approval): bool => $approval->status === ApprovalStatus::Invalidated
        )->values();
    }

    /**
     * The step the document is sitting in right now. Read from the loaded
     * relation so a table column never fires a query per row.
     */
    public function currentApproval(): ?Approval
    {
        return $this->activeApprovals()
            ->firstWhere('status', ApprovalStatus::Pending);
    }

    public function approvalFor(?User $user): ?Approval
    {
        return $user
            ? $this->activeApprovals()->firstWhere('user_id', $user->getKey())
            : null;
    }

    public function awaitsApprovalFrom(?User $user): bool
    {
        return $this->approvalFor($user)?->status === ApprovalStatus::Pending;
    }

    /**
     * A veto may come from anyone still in the queue, not only from whoever
     * holds the current step: an approver who can already see the answer is no
     * should not have to wait their turn to say so.
     */
    public function acceptsRejectionFrom(?User $user): bool
    {
        return in_array(
            $this->approvalFor($user)?->status,
            [ApprovalStatus::Pending, ApprovalStatus::Queued],
            true,
        );
    }

    public function hasApprovalChain(): bool
    {
        return $this->activeApprovals()->isNotEmpty();
    }

    public function approvalProgress(): ?string
    {
        $approvals = $this->activeApprovals();

        if ($approvals->isEmpty()) {
            return null;
        }

        return $approvals->where('status', ApprovalStatus::Approved)->count().'/'.$approvals->count();
    }
}
