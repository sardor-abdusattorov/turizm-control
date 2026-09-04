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

    public function returnToWork(Model $record): void
    {
        if ($record->status !== RequisitionStatus::Rejected) {
            throw new RuntimeException(__('app.approval.error.not_rejected'));
        }

        DB::transaction(function () use ($record): void {
            $lastRound = (int) $record->approvals()->max('round');

            $userIds = $record->approvals()
                ->where('round', $lastRound)
                ->orderBy('order')
                ->pluck('user_id')
                ->all();

            $this->chain->invalidateAll($record);
            $this->chain->sync($record, $userIds);

            $record->update([
                'status' => RequisitionStatus::Draft,
                'submitted_at' => null,
            ]);
        });
    }

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

        if (! $record->userMayApprove($user)) {
            throw new RuntimeException(__('app.approval.error.not_allowed'));
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
