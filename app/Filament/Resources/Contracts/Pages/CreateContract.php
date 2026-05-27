<?php

namespace App\Filament\Resources\Contracts\Pages;

use App\Filament\Resources\Contracts\ContractResource;
use App\Filament\Resources\Contracts\Schemas\ContractForm;
use App\Models\Contact;
use App\Models\ContractTemplate;
use App\Models\Currency;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\HasWizard;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard\Step;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class CreateContract extends CreateRecord
{
    use HasWizard;

    protected static string $resource = ContractResource::class;

    /**
     * @var array<int, array{user_id: int}>
     */
    protected array $pendingApprovers = [];

    /**
     * Selected preview language for the right-hand panel of Step 2.
     * Bound to wire:click on the language pills.
     */
    public string $previewLocale = 'ru';

    public function setPreviewLocale(string $locale): void
    {
        if (in_array($locale, ['ru', 'uz', 'en'], true)) {
            $this->previewLocale = $locale;
        }
    }

    protected function getSteps(): array
    {
        return [
            Step::make(__('app.label.contract_step_basic'))
                ->icon('heroicon-o-document-text')
                ->schema([
                    ...ContractForm::basicSchema(),
                    Section::make(__('app.label.approval_chain'))
                        ->collapsible()
                        ->schema(ContractForm::approversSchema()),
                ]),

            Step::make(__('app.label.contract_step_editor'))
                ->icon('heroicon-o-pencil-square')
                ->schema(fn (Get $get): array => ContractForm::editorSchemaFor(
                    $get('order_type_id') ? (int) $get('order_type_id') : null
                )),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['responsible_id'] = Auth::id();

        // Approvers live in a separate table — pull them out and stash
        // for afterCreate() to attach once the contract row exists.
        $this->pendingApprovers = $data['approvers'] ?? [];
        unset($data['approvers']);

        return $data;
    }

    protected function afterCreate(): void
    {
        foreach ($this->pendingApprovers as $index => $approver) {
            $userId = $approver['user_id'] ?? null;

            if (! $userId) {
                continue;
            }

            $this->record->approvers()->create([
                'user_id' => $userId,
                'order' => $index + 1,
            ]);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }

    /**
     * Live preview HTML for Step 2's right column. Builds system
     * placeholders from the current form state (the contract doesn't
     * exist yet) and merges them with the user-filled template values
     * for the chosen locale.
     */
    public function renderEditorPreview(): string
    {
        $orderTypeId = $this->data['order_type_id'] ?? null;

        if (! $orderTypeId) {
            return '';
        }

        $template = ContractTemplate::defaultForOrderType((int) $orderTypeId);

        if (! $template) {
            return '';
        }

        $userValues = data_get($this->data, "data.{$this->previewLocale}", []);
        $systemValues = $this->systemPlaceholdersFromFormState();

        return $template->render(
            array_merge($systemValues, is_array($userValues) ? $userValues : []),
            $this->previewLocale,
        );
    }

    /**
     * Mirror of Contract::systemPlaceholders() but pulled out of the
     * unsaved form state — used by the Step 2 preview before the
     * contract row exists.
     *
     * @return array<string, string>
     */
    protected function systemPlaceholdersFromFormState(): array
    {
        $contact = ! empty($this->data['contact_id'])
            ? Contact::find($this->data['contact_id'])
            : null;

        $currency = ! empty($this->data['currency_id'])
            ? Currency::find($this->data['currency_id'])
            : null;

        $deadline = ! empty($this->data['deadline_at'])
            ? Carbon::parse($this->data['deadline_at'])->format('d.m.Y')
            : '';

        return [
            'contract_number' => __('app.label.contract_number_auto'),
            'amount' => number_format((float) ($this->data['amount'] ?? 0), 2, '.', ' '),
            'currency_code' => (string) ($currency?->short_name ?? ''),
            'currency_name' => (string) ($currency?->getTranslation('name', $this->previewLocale) ?? ''),
            'contact_name' => (string) ($contact?->getTranslation('name', $this->previewLocale) ?? ''),
            'contact_inn' => (string) ($contact?->inn ?? ''),
            'contact_pinfl' => (string) ($contact?->pinfl ?? ''),
            'contact_address' => (string) ($contact?->getTranslation('address', $this->previewLocale) ?? ''),
            'contact_phone' => (string) ($contact?->phone ?? ''),
            'contact_email' => (string) ($contact?->email ?? ''),
            'contact_person' => (string) ($contact?->contact_person ?? ''),
            'deadline' => $deadline,
            'signed_at' => '',
            'created_at' => now()->format('d.m.Y'),
            'today' => now()->format('d.m.Y'),
        ];
    }
}
