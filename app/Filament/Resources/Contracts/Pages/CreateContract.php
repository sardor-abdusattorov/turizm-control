<?php

namespace App\Filament\Resources\Contracts\Pages;

use App\Filament\Resources\Contracts\ContractResource;
use App\Models\ContractApprover;
use App\Models\ContractTemplate;
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
        $data['language'] = ContractTemplate::find($data['contract_template_id'] ?? null)?->language ?? 'ru';

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

        $order = 1;

        foreach ($this->approverChain as $userId) {
            ContractApprover::create([
                'contract_id' => $this->record->id,
                'user_id' => $userId,
                'order' => $order++,
                'status' => ContractApprover::STATUS_QUEUED,
            ]);
        }

        if (! $this->record->hasApprovers()) {
            $this->record->buildApprovalChainFromFlow();
        }
    }

    protected function getRedirectUrl(): string
    {
        return ContractResource::getUrl('view', ['record' => $this->record]);
    }
}
