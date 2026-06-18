<?php

namespace App\Filament\Resources\Contracts\Schemas;

use App\Filament\Resources\Contacts\Schemas\ContactForm;
use App\Models\Contact;
use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Models\Currency;
use App\Models\Department;
use App\Models\OrderType;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ContractForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make()
                    ->hiddenLabel()
                    ->visible(fn (?Contract $record): bool => $record !== null
                        && in_array($record->status, [Contract::STATUS_IN_REVIEW, Contract::STATUS_APPROVED, Contract::STATUS_REJECTED], true))
                    ->schema([
                        TextEntry::make('edit_warning')
                            ->hiddenLabel()
                            ->state(fn (): string => '⚠️ '.__('app.message.edit_invalidates_approvals'))
                            ->color('warning')
                            ->columnSpanFull(),
                    ]),

                Section::make(__('app.label.attached_document'))
                    ->visible(fn (?Contract $record): bool => $record !== null && $record->documentExists())
                    ->schema([
                        TextEntry::make('document_file')
                            ->hiddenLabel()
                            ->icon('heroicon-o-document-text')
                            ->iconColor('primary')
                            ->weight('bold')
                            ->state(fn (Contract $record): string => $record->number.'.docx')
                            ->columnSpanFull(),

                        TextEntry::make('document_size')
                            ->label(__('app.label.size'))
                            ->state(function (Contract $record): string {
                                $bytes = Storage::disk('local')->size($record->documentPath());

                                return number_format($bytes / 1024, 1).' KB';
                            }),

                        TextEntry::make('created_at')
                            ->label(__('app.label.created_at'))
                            ->dateTime('d.m.Y H:i'),

                        Actions::make([
                            Action::make('openInEditor')
                                ->label(__('app.action.open_editor'))
                                ->icon('heroicon-o-pencil-square')
                                ->color('primary')
                                ->url(fn (Contract $record) => route('contracts.editor', [
                                    'contract' => $record,
                                    'mode' => 'edit',
                                ])),
                        ])->columnSpanFull(),
                    ]),

                Section::make(__('app.label.basic_information'))
                    ->collapsible()
                    ->schema([
                        TextInput::make('number')
                            ->label(__('app.label.contract_number'))
                            ->required()
                            ->maxLength(50)
                            ->unique('contracts', 'number', ignoreRecord: true)
                            ->columnSpanFull(),

                        Select::make('order_type_id')
                            ->label(__('app.label.order_type_single'))
                            ->options(OrderType::getActive())
                            ->required()
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('contract_template_id', null))
                            ->helperText(__('app.helper.contract_order_type'))
                            ->columnSpanFull(),

                        Select::make('contract_template_id')
                            ->label(__('app.label.contract_template_single'))
                            ->options(fn (Get $get): array => self::templateOptions($get('order_type_id')))
                            ->disabled(fn (Get $get): bool => blank($get('order_type_id')))
                            ->placeholder(fn (Get $get): string => blank($get('order_type_id'))
                                ? __('app.label.select_order_type_first')
                                : __('app.label.select_option'))
                            ->required()
                            ->searchable()
                            ->helperText(__('app.helper.contract_template_choice'))
                            ->columnSpanFull(),

                        Select::make('contact_id')
                            ->label(__('app.label.contact_single'))
                            ->options(Contact::getActive())
                            ->required()
                            ->searchable()
                            ->preload()
                            ->createOptionForm(fn (Schema $schema) => ContactForm::configure($schema))
                            ->createOptionUsing(fn (array $data) => Contact::create($data)->getKey())
                            ->columnSpanFull(),

                        TextInput::make('title')
                            ->label(__('app.label.contract_title'))
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        TextInput::make('amount')
                            ->label(__('app.label.amount'))
                            ->numeric()
                            ->default(0)
                            ->required()
                            ->columnSpanFull(),

                        Select::make('currency_id')
                            ->label(__('app.label.currency_single'))
                            ->options(Currency::query()->where('status', true)->pluck('short_name', 'id'))
                            ->required()
                            ->searchable()
                            ->preload()
                            ->columnSpanFull(),
                    ]),

                Section::make(__('app.label.approval_chain'))
                    ->description(__('app.helper.approval_chain_form'))
                    ->collapsible()
                    ->hiddenOn('edit')
                    ->schema([
                        Repeater::make('approver_chain')
                            ->hiddenLabel()
                            ->reorderable()
                            ->reorderableWithDragAndDrop()
                            ->default(fn (): array => self::defaultApproverRows())
                            ->addActionLabel(__('app.action.add_approver'))
                            ->schema([
                                Select::make('user_id')
                                    ->label(__('app.label.approver'))
                                    ->options(self::approverOptions())
                                    ->required()
                                    ->searchable(),
                            ])
                            ->itemLabel(function (array $state): ?string {
                                $id = $state['user_id'] ?? null;

                                return $id ? (User::find($id)?->name ?? '') : null;
                            }),
                    ]),
            ]);
    }

    /**
     * Active templates for the chosen order type, plus untyped "general"
     * templates that apply to any type. Empty until a type is selected so the
     * dropdown stays gated behind the order type.
     *
     * @return array<int, string>
     */
    protected static function templateOptions(?int $orderTypeId): array
    {
        if (blank($orderTypeId)) {
            return [];
        }

        return ContractTemplate::query()
            ->active()
            ->where(fn ($query) => $query
                ->where('order_type_id', $orderTypeId)
                ->orWhereNull('order_type_id'))
            ->orderBy('sort')
            ->get()
            ->mapWithKeys(fn (ContractTemplate $t): array => [
                $t->id => $t->name.' ('.strtoupper($t->language).')',
            ])
            ->toArray();
    }

    /**
     * All active users as id => "Name · Department / Position" options.
     *
     * @return array<int, string>
     */
    protected static function approverOptions(): array
    {
        return User::query()
            ->where('status', User::STATUS_ACTIVE)
            ->with('department', 'position')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(function (User $user): array {
                $dept = $user->department?->getTranslation('name', app()->getLocale()) ?? '—';
                $pos = $user->position?->getTranslation('name', app()->getLocale()) ?? '';
                $label = $user->name.' · '.$dept.($pos ? ' / '.$pos : '');

                return [$user->id => $label];
            })
            ->toArray();
    }

    /**
     * Pre-fill rows from the manager's profile default recipients, falling
     * back to the global settings flow when the profile has none set.
     *
     * @return array<int, array{user_id: int}>
     */
    protected static function defaultApproverRows(): array
    {
        $user = Auth::user();

        if (! $user) {
            return [];
        }

        $ids = $user->defaultRecipients()
            ->where('users.status', User::STATUS_ACTIVE)
            ->pluck('users.id')
            ->all();

        if (empty($ids)) {
            $ids = collect(Department::approvalFlow())
                ->map(fn (string $code) => Department::findByCode($code)?->approverUser()?->id)
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        return array_map(fn (int $id): array => ['user_id' => $id], $ids);
    }
}
