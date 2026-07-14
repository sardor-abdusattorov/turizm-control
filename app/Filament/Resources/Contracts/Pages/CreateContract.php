<?php

namespace App\Filament\Resources\Contracts\Pages;

use App\Filament\Resources\Contracts\ContractResource;
use App\Services\Contracts\ApprovalChain;
use App\Services\Contracts\ContractWorkflow;
use App\Services\Documents\ContractPlaceholderValues;
use App\Services\Documents\TemplateFiller;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateContract extends CreateRecord
{
    protected static string $resource = ContractResource::class;

    /** @var array<int, int> */
    protected array $approverChain = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['responsible_id'] = Auth::id();

        $this->approverChain = array_values(array_filter(array_map('intval', (array) ($data['approver_chain'] ?? []))));
        unset($data['approver_chain']);

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->record->buildDocumentFromTemplate(
            app(TemplateFiller::class),
            app(ContractPlaceholderValues::class),
        );

        // With approval switched off (Settings → Согласование) the contract
        // is just filed — no chain to build, nothing to submit.
        if (! ContractWorkflow::approvalEnabled()) {
            return;
        }

        app(ApprovalChain::class)->requeue($this->record, $this->approverChain);

        if (! $this->record->hasApprovers()) {
            $this->record->buildApprovalChainFromFlow();
        }
    }

    protected function getRedirectUrl(): string
    {
        return ContractResource::getUrl('view', ['record' => $this->record]);
    }
}
