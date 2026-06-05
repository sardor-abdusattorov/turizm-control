<?php

namespace App\Filament\Resources\Contracts\Schemas;

use App\Filament\Resources\Contacts\Schemas\ContactForm;
use App\Models\Contact;
use App\Models\ContractTemplate;
use App\Models\Currency;
use App\Models\OrderType;
use App\Models\User;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class ContractForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('app.label.basic_information'))
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 2])
                            ->schema([
                                TextInput::make('number')
                                    ->label(__('app.label.contract_number'))
                                    ->required()
                                    ->maxLength(50)
                                    ->unique('contracts', 'number', ignoreRecord: true),

                                Select::make('order_type_id')
                                    ->label(__('app.label.order_type_single'))
                                    ->options(OrderType::getActive())
                                    ->searchable()
                                    ->preload()
                                    ->placeholder(__('app.label.no_category'))
                                    ->helperText(__('app.helper.contract_order_type')),

                                Select::make('contract_template_id')
                                    ->label(__('app.label.contract_template_single'))
                                    ->options(
                                        ContractTemplate::query()
                                            ->active()
                                            ->orderBy('sort')
                                            ->get()
                                            ->mapWithKeys(fn (ContractTemplate $t): array => [
                                                $t->id => $t->name.' ('.strtoupper($t->language).')',
                                            ])
                                            ->toArray()
                                    )
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->helperText(__('app.helper.contract_template_choice')),

                                Select::make('contact_id')
                                    ->label(__('app.label.contact_single'))
                                    ->options(Contact::getActive())
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->createOptionForm(fn (Schema $schema) => ContactForm::configure($schema))
                                    ->createOptionUsing(fn (array $data) => Contact::create($data)->getKey()),

                                TextInput::make('title')
                                    ->label(__('app.label.contract_title'))
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpanFull(),
                            ]),

                        Grid::make(['default' => 1, 'md' => 2])
                            ->schema([
                                TextInput::make('amount')
                                    ->label(__('app.label.amount'))
                                    ->numeric()
                                    ->default(0)
                                    ->required(),

                                Select::make('currency_id')
                                    ->label(__('app.label.currency_single'))
                                    ->options(Currency::query()->where('status', true)->pluck('short_name', 'id'))
                                    ->required()
                                    ->searchable()
                                    ->preload(),
                            ]),
                    ]),

                Section::make(__('app.label.approval_chain'))
                    ->description(__('app.helper.approval_chain_form'))
                    ->collapsible()
                    ->schema([
                        Repeater::make('approver_chain')
                            ->hiddenLabel()
                            ->reorderable()
                            ->reorderableWithDragAndDrop()
                            ->default(fn () => self::defaultApproverRows())
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

    protected static function defaultApproverRows(): array
    {
        $user = Auth::user();

        if (! $user) {
            return [];
        }

        return $user->defaultRecipients()
            ->where('users.status', User::STATUS_ACTIVE)
            ->pluck('users.id')
            ->map(fn (int $id): array => ['user_id' => $id])
            ->toArray();
    }
}
