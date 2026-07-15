<?php

namespace App\Filament\Resources\Contracts\Pages;

use App\Filament\Resources\Contracts\ContractResource;
use App\Filament\Resources\Contracts\Pages\Concerns\HandlesDossierUploads;
use App\Models\Contract;
use App\Services\Contracts\ApprovalChain;
use App\Services\Contracts\ContractWorkflow;
use App\Services\Documents\ContractPlaceholderValues;
use App\Services\Documents\TemplateFiller;
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

        // Legacy import switch: keep the flag aside, the record is flipped to
        // approved after create. The signing date only applies in that case.
        $this->alreadySigned = (bool) ($data['already_signed'] ?? false);
        unset($data['already_signed']);

        if (! $this->alreadySigned) {
            unset($data['signed_at']);
        }

        // Scans uploaded on the form become dossier attachments in afterCreate.
        return $this->extractAttachmentUploads($data);
    }

    protected function afterCreate(): void
    {
        $this->storeFormAttachments();

        $this->record->buildDocumentFromTemplate(
            app(TemplateFiller::class),
            app(ContractPlaceholderValues::class),
        );

        // A legacy contract is already signed on paper: file it as approved
        // straight away — no chain, no notifications (same quiet path the
        // dossier importer uses).
        if ($this->alreadySigned) {
            $this->record->forceFill(['status' => Contract::STATUS_APPROVED])->saveQuietly();

            return;
        }

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
