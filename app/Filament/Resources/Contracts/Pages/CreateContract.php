<?php

namespace App\Filament\Resources\Contracts\Pages;

use App\Filament\Resources\Contracts\ContractResource;
use App\Filament\Resources\Contracts\Pages\Concerns\HandlesDossierUploads;
use App\Models\Contract;
use App\Services\Contracts\ApprovalChain;
use App\Services\Contracts\ContractFiles;
use App\Services\Contracts\ContractWorkflow;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateContract extends CreateRecord
{
    use HandlesDossierUploads;

    protected static string $resource = ContractResource::class;

    /** @var array<int, int> */
    protected array $approverChain = [];

    protected bool $alreadySigned = false;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['responsible_id'] = Auth::id();

        $this->approverChain = array_values(array_filter(array_map('intval', (array) ($data['approver_chain'] ?? []))));
        unset($data['approver_chain']);

        $this->alreadySigned = (bool) ($data['already_signed'] ?? false);
        unset($data['already_signed']);

        if (! $this->alreadySigned) {
            unset($data['signed_at']);
        }

        return $this->extractAttachmentUploads($data);
    }

    protected function afterCreate(): void
    {
        app(ContractFiles::class)->purge($this->record);

        $this->storeFormAttachments();

        if ($this->alreadySigned) {
            $this->record->forceFill(['status' => Contract::STATUS_APPROVED])->saveQuietly();

            return;
        }

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
