<?php

declare(strict_types=1);

namespace App\Services\Approvals;

use App\Enums\ApprovalStatus;
use App\Enums\RequisitionStatus;
use App\Models\Approval;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ApprovalWorkflow
{
    public function __construct(private readonly ApprovalChain $chain) {}

    public function submit(Model $record): void
    {
        if ($record->status !== RequisitionStatus::Draft) {
            throw new RuntimeException(__('app.approval.error.not_draft'));
        }

        if (! $record->hasApprovalChain()) {
            throw new RuntimeException(__('app.approval.error.no_approvers'));
        }

        DB::transaction(function () use ($record): void {
            $record->update([
                'status' => RequisitionStatus::InReview,
                'submitted_at' => now(),
            ]);

            $this->openNextStep($record);
        });
    }

    public function approve(Model $record, User $user, ?string $comment = null): void
    {
        $approval = $this->actionableApproval($record, $user);

        DB::transaction(function () use ($record, $approval, $comment): void {
            $approval->approve($comment);

            $record->unsetRelation('approvals');

            $stillOpen = $record->approvals()
                ->active()
                ->whereIn('status', [ApprovalStatus::Queued, ApprovalStatus::Pending])
                ->exists();

            if ($stillOpen) {
                $this->openNextStep($record);

                return;
            }

            $record->update(['status' => RequisitionStatus::Approved]);
        });
    }

    /**
     * A refusal ends the round: the rest of the queue is voided so nobody is
     * left holding a step on a document that is already going back, and the
     * verdict with its reason stays on the record forever.
     */
    public function reject(Model $record, User $user, ?string $comment = null): void
    {
        $approval = $this->actionableApproval($record, $user, allowQueued: true);

        DB::transaction(function () use ($record, $approval, $comment): void {
            $approval->reject($comment);

            $record->approvals()
                ->active()
                ->whereIn('status', [ApprovalStatus::Queued, ApprovalStatus::Pending])
                ->get()
                ->each(fn (Approval $queued) => $queued->invalidate());

            $record->update(['status' => RequisitionStatus::Rejected]);

            $record->unsetRelation('approvals');
        });
    }

    /**
     * The author pulls the document back out of the flow — nobody is asked any
     * more and it returns to a draft they can work on.
     */
    public function recall(Model $record): void
    {
        if ($record->status === RequisitionStatus::Draft) {
            return;
        }

        DB::transaction(function () use ($record): void {
            $this->chain->invalidateAll($record);

            $record->update([
                'status' => RequisitionStatus::Draft,
                'submitted_at' => null,
            ]);
        });
    }

    /**
     * A settled document that gets edited goes back to draft: the verdicts it
     * collected were given on text that no longer exists.
     */
    public function resetAfterEdit(Model $record): void
    {
        if (! in_array($record->status, [RequisitionStatus::Approved, RequisitionStatus::Rejected], true)) {
            return;
        }

        DB::transaction(function () use ($record): void {
            $this->chain->invalidateAll($record);

            $record->update(['status' => RequisitionStatus::Draft]);
        });
    }

    /**
     * An edit lands mid-review: the round that was running is voided and the
     * same people are asked again from the top. The user list is read before
     * the void so people dropped from the chain earlier are not dragged back.
     */
    public function restartAfterEdit(Model $record): void
    {
        if ($record->status !== RequisitionStatus::InReview) {
            return;
        }

        DB::transaction(function () use ($record): void {
            $userIds = $record->approvals()
                ->active()
                ->orderBy('order')
                ->pluck('user_id')
                ->all();

            $this->chain->invalidateAll($record);
            $this->chain->sync($record, $userIds);

            $this->openNextStep($record);
        });
    }

    protected function openNextStep(Model $record): void
    {
        $this->chain->nextInLine($record)
            ->each(fn (Approval $approval) => $approval->startReview($record::reviewDays()));

        $record->unsetRelation('approvals');
    }

    protected function actionableApproval(Model $record, User $user, bool $allowQueued = false): Approval
    {
        if ($record->status !== RequisitionStatus::InReview) {
            throw new RuntimeException(__('app.approval.error.not_in_review'));
        }

        $approval = $record->approvalFor($user);

        if (! $approval) {
            throw new RuntimeException(__('app.approval.error.not_an_approver'));
        }

        if ($approval->status === ApprovalStatus::Queued && $allowQueued) {
            return $approval;
        }

        if ($approval->status !== ApprovalStatus::Pending) {
            throw new RuntimeException(
                $approval->status === ApprovalStatus::Queued
                    ? __('app.approval.error.waiting_for_previous')
                    : __('app.approval.error.already_decided')
            );
        }

        return $approval;
    }
}
