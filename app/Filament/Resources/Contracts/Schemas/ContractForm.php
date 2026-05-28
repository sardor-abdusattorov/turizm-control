<?php

namespace App\Filament\Resources\Contracts\Schemas;

use AbdulmajeedJamaan\FilamentTranslatableTabs\TranslatableTabs;
use App\Filament\Resources\Contacts\Schemas\ContactForm;
use App\Models\Contact;
use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Models\Currency;
use App\Models\OrderType;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\View as SchemaView;
use Filament\Schemas\Schema;

class ContractForm
{
    /**
     * Full edit-form composition: basic + items + approvers + document
     * fields. Used by EditContract (the create wizard pulls the parts
     * it needs directly via basicSchema / itemsSchema / approversSchema).
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('app.label.basic_information'))
                    ->schema(self::basicSchema()),

                Section::make(__('app.label.contract_items'))
                    ->description(__('app.helper.contract_items'))
                    ->collapsible()
                    ->collapsed()
                    ->schema(self::itemsSchema(relationship: true)),

                Section::make(__('app.label.approval_chain'))
                    ->description(__('app.helper.approval_chain'))
                    ->collapsible()
                    ->schema(self::approversSchema(relationship: true)),

                Section::make(__('app.label.contract_data'))
                    ->collapsible()
                    ->collapsed()
                    ->schema(fn (?Contract $record): array => self::documentFieldsForRecord($record)),
            ]);
    }

    /**
     * Fields that describe the contract itself (type, parties, terms).
     * Used both by the create wizard's first step and by the edit form.
     *
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    public static function basicSchema(): array
    {
        return [
            Grid::make(['default' => 1, 'md' => 2])
                ->schema([
                    Select::make('order_type_id')
                        ->label(__('app.label.order_type_single'))
                        ->options(OrderType::getActive())
                        ->required()
                        ->searchable()
                        ->preload()
                        ->live(),

                    Select::make('contact_id')
                        ->label(__('app.label.contact_single'))
                        ->options(Contact::getActive())
                        ->required()
                        ->searchable()
                        ->preload()
                        ->createOptionForm(fn (Schema $schema) => ContactForm::configure($schema))
                        ->createOptionUsing(fn (array $data) => Contact::create($data)->getKey()),
                ]),

            TranslatableTabs::make()
                ->schema([
                    TextInput::make('title')
                        ->label(__('app.label.title'))
                        ->required()
                        ->maxLength(255),

                    Textarea::make('description')
                        ->label(__('app.label.description'))
                        ->rows(3),
                ]),

            Grid::make(['default' => 1, 'md' => 3])
                ->schema([
                    TextInput::make('amount')
                        ->label(__('app.label.amount'))
                        ->numeric()
                        ->inputMode('decimal')
                        ->step(0.01)
                        ->default(0)
                        ->required(),

                    Select::make('currency_id')
                        ->label(__('app.label.currency_single'))
                        ->options(Currency::getActive())
                        ->required()
                        ->searchable()
                        ->preload(),

                    DatePicker::make('deadline_at')
                        ->label(__('app.label.deadline'))
                        ->native(false)
                        ->displayFormat('d.m.Y'),
                ]),
        ];
    }

    /**
     * Multi-row "items" repeater — for purchase-order style documents
     * (Заявка на закупку, etc.) where the body is a table of
     * specifications. Optional: if the template doesn't use the
     * {{purchase_items}} placeholder the section just stays empty.
     *
     * When relationship=true the repeater binds to the HasMany so
     * Filament handles loading existing rows + sync-on-save (used by
     * EditContract). When false the repeater is plain array state and
     * the page is responsible for persisting (used by the create
     * wizard's afterCreate).
     *
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    public static function itemsSchema(bool $relationship = false): array
    {
        $repeater = Repeater::make('items');

        if ($relationship) {
            $repeater->relationship();
        }

        return [
            $repeater
                ->label(__('app.label.contract_items'))
                ->addActionLabel(__('app.action.add_item'))
                ->reorderable()
                ->reorderableWithDragAndDrop()
                ->orderColumn('sort')
                ->collapsible()
                ->itemLabel(function (array $state): ?string {
                    $spec = $state['specification'] ?? null;

                    if (is_array($spec)) {
                        $spec = $spec[app()->getLocale()]
                            ?? $spec['ru']
                            ?? array_values($spec)[0]
                            ?? null;
                    }

                    return is_string($spec) && $spec !== '' ? $spec : null;
                })
                ->schema([
                    TranslatableTabs::make()
                        ->schema([
                            Textarea::make('specification')
                                ->label(__('app.label.items_specification'))
                                ->rows(2)
                                ->required(),
                            TextInput::make('counterparty')
                                ->label(__('app.label.items_counterparty')),
                        ]),

                    Grid::make(['default' => 1, 'md' => 2])
                        ->schema([
                            TextInput::make('amount')
                                ->label(__('app.label.items_amount'))
                                ->numeric()
                                ->default(0),
                            TextInput::make('agreement_ref')
                                ->label(__('app.label.items_agreement_ref'))
                                ->maxLength(255),
                        ]),
                ]),
        ];
    }

    /**
     * Approval chain — used by the wizard's Step 1 (collapsible section)
     * and the edit form. Pre-fills with the manager's defaultRecipients
     * ordered by the admin-configured approval flow.
     *
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    public static function approversSchema(bool $relationship = false): array
    {
        $repeater = Repeater::make('approvers');

        if ($relationship) {
            $repeater->relationship();
        }

        return [
            $repeater
                ->label(__('app.label.approval_chain'))
                ->helperText(__('app.helper.approval_chain'))
                ->schema([
                    Select::make('user_id')
                        ->label(__('app.label.approver'))
                        ->options(User::approverOptionsForChain())
                        ->required()
                        ->searchable(),
                ])
                ->itemLabel(function (array $state): ?string {
                    if (empty($state['user_id'])) {
                        return null;
                    }

                    return User::find($state['user_id'])?->name;
                })
                ->reorderable()
                ->reorderableWithDragAndDrop()
                ->orderColumn('order')
                ->addActionLabel(__('app.action.add_approver'))
                ->minItems(1)
                ->default(fn () => $relationship ? null : Contract::suggestApproverChain())
                ->collapsible(),
        ];
    }

    /**
     * Document-fields editor for the edit page. Same locale-tabbed
     * inputs as the wizard's split-editor but without the right-side
     * preview. Built dynamically from the record's order_type
     * template; returns an empty array if no template or no fields.
     *
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    public static function documentFieldsForRecord(?Contract $record): array
    {
        if (! $record || ! $record->order_type_id) {
            return [];
        }

        $template = ContractTemplate::defaultForOrderType($record->order_type_id);

        if (! $template) {
            return [];
        }

        $fields = is_array($template->fields) ? $template->fields : [];

        if (empty($fields)) {
            return [];
        }

        $localeTabs = [];

        foreach (['ru', 'uz', 'en'] as $locale) {
            $tabFields = [];

            foreach ($fields as $field) {
                $name = $field['name'] ?? null;

                if (! $name) {
                    continue;
                }

                $statePath = "data.{$locale}.{$name}";
                $label = $field['label'] ?? $name;
                $type = $field['type'] ?? 'text';
                $required = (bool) ($field['required'] ?? false);

                $tabFields[] = match ($type) {
                    'textarea' => Textarea::make($statePath)
                        ->label($label)
                        ->rows(3)
                        ->required($required),
                    'number' => TextInput::make($statePath)
                        ->label($label)
                        ->numeric()
                        ->required($required),
                    'date' => DatePicker::make($statePath)
                        ->label($label)
                        ->native(false)
                        ->displayFormat('d.m.Y')
                        ->required($required),
                    default => TextInput::make($statePath)
                        ->label($label)
                        ->required($required),
                };
            }

            $localeTabs[] = Tab::make(strtoupper($locale))->schema($tabFields);
        }

        return [
            Tabs::make()->tabs($localeTabs),
        ];
    }

    /**
     * Step 2 of the create wizard: split-screen contract editor. Left
     * column has language-tabbed inputs built from the template's
     * fields schema. Right column has the live preview (reads form
     * state and renders the template body with placeholders filled).
     *
     * Dynamic because it depends on the order_type chosen in Step 1.
     *
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    public static function editorSchemaFor(?int $orderTypeId): array
    {
        if (! $orderTypeId) {
            return [
                Section::make()
                    ->schema([
                        SchemaView::make('filament.components.contract-editor-empty')
                            ->viewData(['message' => __('app.message.select_order_type_first')]),
                    ]),
            ];
        }

        $template = ContractTemplate::defaultForOrderType($orderTypeId);

        if (! $template) {
            return [
                Section::make()
                    ->schema([
                        SchemaView::make('filament.components.contract-editor-empty')
                            ->viewData(['message' => __('app.message.no_template_for_type')]),
                    ]),
            ];
        }

        $fields = is_array($template->fields) ? $template->fields : [];

        $localeTabs = [];

        foreach (['ru', 'uz', 'en'] as $locale) {
            $tabFields = [];

            foreach ($fields as $field) {
                $name = $field['name'] ?? null;

                if (! $name) {
                    continue;
                }

                $statePath = "data.{$locale}.{$name}";
                $label = $field['label'] ?? $name;
                $type = $field['type'] ?? 'text';
                $required = (bool) ($field['required'] ?? false);

                $tabFields[] = match ($type) {
                    'textarea' => Textarea::make($statePath)
                        ->label($label)
                        ->rows(3)
                        ->required($required)
                        ->live(debounce: 500),
                    'number' => TextInput::make($statePath)
                        ->label($label)
                        ->numeric()
                        ->required($required)
                        ->live(debounce: 500),
                    'date' => DatePicker::make($statePath)
                        ->label($label)
                        ->native(false)
                        ->displayFormat('d.m.Y')
                        ->required($required)
                        ->live(),
                    default => TextInput::make($statePath)
                        ->label($label)
                        ->required($required)
                        ->live(debounce: 500),
                };
            }

            if (empty($tabFields)) {
                $tabFields[] = SchemaView::make('filament.components.contract-editor-empty')
                    ->viewData(['message' => __('app.message.template_has_no_fields')]);
            }

            $localeTabs[] = Tab::make(strtoupper($locale))->schema($tabFields);
        }

        return [
            Grid::make(['default' => 1, 'lg' => 2])
                ->schema([
                    Section::make(__('app.label.contract_data'))
                        ->columnSpan(1)
                        ->schema([
                            Tabs::make()->tabs($localeTabs),
                        ]),

                    Section::make(__('app.label.preview'))
                        ->columnSpan(1)
                        ->schema([
                            SchemaView::make('filament.components.contract-editor-preview'),
                        ]),
                ]),
        ];
    }
}
