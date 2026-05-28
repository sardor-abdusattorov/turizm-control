<?php

namespace App\Filament\Resources\Contracts\Pages;

use App\Filament\Resources\Contracts\ContractResource;
use App\Filament\Resources\Contracts\Schemas\ContractForm;
use App\Models\Contact;
use App\Models\Contract;
use App\Models\ContractApprover;
use App\Models\ContractTemplate;
use App\Models\Currency;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\HasWizard;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard\Step;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

class CreateContract extends CreateRecord
{
    use HasWizard;

    protected static string $resource = ContractResource::class;

    /**
     * @var array<int, array{user_id: int}>
     */
    protected array $pendingApprovers = [];

    /**
     * @var array<int, array<string, mixed>>
     */
    protected array $pendingItems = [];

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

                    Section::make(__('app.label.contract_items'))
                        ->description(__('app.helper.contract_items'))
                        ->collapsible()
                        ->collapsed()
                        ->schema(ContractForm::itemsSchema()),

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

        $this->pendingApprovers = $data['approvers'] ?? [];
        $this->pendingItems = $data['items'] ?? [];

        unset($data['approvers'], $data['items']);

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

        foreach ($this->pendingItems as $index => $item) {
            $spec = $item['specification'] ?? null;

            if (empty($spec)) {
                continue;
            }

            $this->record->items()->create([
                'sort' => $index + 1,
                'specification' => $item['specification'] ?? [],
                'counterparty' => $item['counterparty'] ?? [],
                'amount' => $item['amount'] ?? 0,
                'agreement_ref' => $item['agreement_ref'] ?? null,
            ]);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }

    /**
     * Live preview HTML for Step 2. Mimics what the saved Contract
     * would render: pulls system placeholders from the unsaved form
     * state, builds in-memory approvers/items "blocks" for the
     * preview, then merges with the manager-typed user values for
     * the chosen locale.
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
     * @return array<string, scalar|HtmlString>
     */
    protected function systemPlaceholdersFromFormState(): array
    {
        $locale = $this->previewLocale;

        $contact = ! empty($this->data['contact_id'])
            ? Contact::find($this->data['contact_id'])
            : null;

        $currency = ! empty($this->data['currency_id'])
            ? Currency::find($this->data['currency_id'])
            : null;

        $deadline = ! empty($this->data['deadline_at'])
            ? Carbon::parse($this->data['deadline_at'])->format('d.m.Y')
            : '';

        $responsible = Auth::user();

        return array_merge(
            Contract::organizationPlaceholders($locale),
            [
                'contract_number' => __('app.label.contract_number_auto'),
                'amount' => number_format((float) ($this->data['amount'] ?? 0), 2, '.', ' '),
                'currency_code' => (string) ($currency?->short_name ?? ''),
                'currency_name' => (string) ($currency?->getTranslation('name', $locale) ?? ''),
                'contact_name' => (string) ($contact?->getTranslation('name', $locale) ?? ''),
                'contact_inn' => (string) ($contact?->inn ?? ''),
                'contact_pinfl' => (string) ($contact?->pinfl ?? ''),
                'contact_address' => (string) ($contact?->getTranslation('address', $locale) ?? ''),
                'contact_phone' => (string) ($contact?->phone ?? ''),
                'contact_email' => (string) ($contact?->email ?? ''),
                'contact_person' => (string) ($contact?->contact_person ?? ''),
                'manager_name' => (string) ($responsible?->name ?? ''),
                'manager_position' => (string) ($responsible?->department?->getTranslation('name', $locale) ?? ''),
                'deadline' => $deadline,
                'signed_at' => '',
                'created_at' => now()->format('d.m.Y'),
                'today' => now()->format('d.m.Y'),
                'approvers_block' => new HtmlString($this->renderApproversBlockPreview($locale, $responsible)),
                'purchase_items' => new HtmlString($this->renderItemsTablePreview($locale, $currency?->short_name ?? '')),
            ],
        );
    }

    /**
     * Build a preview approvers block from the form state — uses the
     * same blade view as the saved render, but feeds it
     * ContractApprover *instances in memory* (no DB persistence).
     */
    protected function renderApproversBlockPreview(string $locale, ?User $responsible): string
    {
        $rows = collect($this->data['approvers'] ?? [])
            ->map(function (array $row, int $index): ?ContractApprover {
                if (empty($row['user_id'])) {
                    return null;
                }

                return tap(new ContractApprover([
                    'user_id' => $row['user_id'],
                    'order' => $index + 1,
                    'status' => ContractApprover::STATUS_PENDING,
                ]), function (ContractApprover $approver) use ($row): void {
                    $approver->setRelation('user', User::with('department')->find($row['user_id']));
                });
            })
            ->filter()
            ->values();

        if ($rows->isEmpty() && ! $responsible) {
            return '';
        }

        return view('contract.approvers-block', [
            'approvers' => $rows,
            'locale' => $locale,
            'responsible' => $responsible,
        ])->render();
    }

    /**
     * Build a preview items table from the form state — items aren't
     * persisted yet, so we feed the blade an in-memory ContractItem
     * collection.
     */
    protected function renderItemsTablePreview(string $locale, string $currencyCode): string
    {
        $items = collect($this->data['items'] ?? [])
            ->map(function (array $row, int $index): \App\Models\ContractItem {
                $item = new \App\Models\ContractItem([
                    'sort' => $index + 1,
                    'specification' => $row['specification'] ?? [],
                    'counterparty' => $row['counterparty'] ?? [],
                    'amount' => $row['amount'] ?? 0,
                    'agreement_ref' => $row['agreement_ref'] ?? null,
                ]);

                return $item;
            })
            ->filter(function (\App\Models\ContractItem $item) use ($locale): bool {
                return ! empty($item->getTranslation('specification', $locale, false));
            })
            ->values();

        if ($items->isEmpty()) {
            return '';
        }

        return view('contract.items-table', [
            'items' => $items,
            'locale' => $locale,
            'currencyCode' => $currencyCode,
        ])->render();
    }
}
