<?php

namespace App\Filament\Resources\Contracts\Pages;

use App\Filament\Resources\Contracts\ContractResource;
use App\Models\Contact;
use App\Models\ContractApprover;
use App\Models\ContractTemplate;
use App\Models\Currency;
use App\Models\OrderType;
use App\Models\User;
use App\Services\Documents\ContractPlaceholderValues;
use App\Services\Documents\TemplateFiller;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateContract extends CreateRecord
{
    protected static string $resource = ContractResource::class;

    protected string $view = 'filament.resources.contracts.pages.create-contract';

    /**
     * Live summary read from the form state for the sticky preview pane.
     *
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $raw = $this->form->getRawState();
        $data = is_array($raw) ? $raw : $raw->toArray();

        $template = ! empty($data['contract_template_id']) ? ContractTemplate::find($data['contract_template_id']) : null;
        $contact = ! empty($data['contact_id']) ? Contact::find($data['contact_id']) : null;
        $orderType = ! empty($data['order_type_id']) ? OrderType::find($data['order_type_id']) : null;
        $currency = ! empty($data['currency_id']) ? Currency::find($data['currency_id']) : null;

        $approverIds = array_values(array_filter(array_map('intval', (array) ($data['approver_chain'] ?? []))));
        $approvers = $approverIds
            ? User::with(['department', 'position'])->whereIn('id', $approverIds)->get()->keyBy('id')
            : collect();

        $chain = collect($approverIds)
            ->map(fn (int $id) => $approvers->get($id))
            ->filter()
            ->values();

        return [
            'number' => trim((string) ($data['number'] ?? '')),
            'title' => trim((string) ($data['title'] ?? '')),
            'amount' => is_numeric($data['amount'] ?? null) && (float) $data['amount'] > 0 ? (float) $data['amount'] : null,
            'currency' => $currency?->short_name,
            'contact' => $contact,
            'template' => $template,
            'order_type' => $orderType,
            'chain' => $chain,
        ];
    }

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

        // Fall back to the configured settings queue when nothing was picked.
        if (! $this->record->hasApprovers()) {
            $this->record->buildApprovalChainFromFlow();
        }
    }

    protected function getRedirectUrl(): string
    {
        return ContractResource::getUrl('view', ['record' => $this->record]);
    }
}
