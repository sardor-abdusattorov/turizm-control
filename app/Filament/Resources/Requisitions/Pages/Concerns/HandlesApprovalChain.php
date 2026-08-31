<?php

namespace App\Filament\Resources\Requisitions\Pages\Concerns;

use App\Services\Approvals\ApprovalChain;
use App\Services\Approvals\ApprovalWorkflow;

trait HandlesApprovalChain
{
    /** @var array<int, int> */
    protected array $approverIds = [];

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function captureApprovers(array $data): array
    {
        $this->approverIds = array_values(array_filter(array_map('intval', (array) ($data['approver_ids'] ?? []))));

        unset($data['approver_ids']);

        return $data;
    }

    protected function syncChain(): void
    {
        app(ApprovalChain::class)->sync($this->record, $this->approverIds);
        $this->record->unsetRelation('approvals');
    }

    /**
     * The edit side effects, in the order the round expects them: a settled
     * record is voided back to draft first, then the chain syncs, then a live
     * round restarts from the first step.
     */
    protected function settleRound(): void
    {
        $workflow = app(ApprovalWorkflow::class);

        $workflow->resetAfterEdit($this->record);
        $this->syncChain();
        $workflow->restartAfterEdit($this->record);
    }
}
