<?php

namespace App\Observers;

use App\Models\ProjectParticipant;
use App\Models\ProjectPayment;

class ProjectPaymentObserver
{
    public function created(ProjectPayment $payment): void
    {
        $this->syncParticipantPaidAmount($payment->project_participant_id);
    }

    public function updated(ProjectPayment $payment): void
    {
        $this->syncParticipantPaidAmount($payment->project_participant_id);

        $original = (int) ($payment->getOriginal('project_participant_id') ?? 0);

        if ($original && $original !== (int) $payment->project_participant_id) {
            $this->syncParticipantPaidAmount($original);
        }
    }

    public function deleted(ProjectPayment $payment): void
    {
        $this->syncParticipantPaidAmount($payment->project_participant_id);
    }

    /**
     * Recompute the participant's cached paid total from its installments and
     * write it back with a bulk update (does not fire participant events).
     */
    private function syncParticipantPaidAmount(?int $participantId): void
    {
        if (! $participantId) {
            return;
        }

        $sum = round((float) ProjectPayment::query()
            ->where('project_participant_id', $participantId)
            ->sum('amount'), 2);

        ProjectParticipant::query()
            ->whereKey($participantId)
            ->update(['paid_amount' => $sum]);
    }
}
