<?php

namespace App\Filament\Resources\Contracts\Schemas;

use App\Filament\Resources\Contacts\Schemas\ContactForm;
use App\Models\Contact;
use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Models\Currency;
use App\Models\OrderType;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

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
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->afterStateUpdated(fn (Set $set) => $set('contract_template_id', null)),

                                Select::make('language')
                                    ->label(__('app.label.language'))
                                    ->options(ContractTemplate::getLanguages())
                                    ->default('ru')
                                    ->required()
                                    ->native(false)
                                    ->live()
                                    ->afterStateUpdated(fn (Set $set) => $set('contract_template_id', null)),

                                Select::make('contract_template_id')
                                    ->label(__('app.label.contract_template_single'))
                                    ->options(function (Get $get): array {
                                        $type = $get('order_type_id');
                                        $language = $get('language') ?: 'ru';

                                        if (! $type) {
                                            return [];
                                        }

                                        return ContractTemplate::query()
                                            ->where('order_type_id', $type)
                                            ->where('language', $language)
                                            ->active()
                                            ->orderBy('sort')
                                            ->pluck('name', 'id')
                                            ->toArray();
                                    })
                                    ->required()
                                    ->searchable()
                                    ->disabled(fn (Get $get): bool => ! $get('order_type_id'))
                                    ->helperText(__('app.helper.contract_template_choice')),
                            ]),

                        TextInput::make('title')
                            ->label(__('app.label.contract_title'))
                            ->required()
                            ->maxLength(255),

                        Grid::make(['default' => 1, 'md' => 2])
                            ->schema([
                                Select::make('contact_id')
                                    ->label(__('app.label.contact_single'))
                                    ->options(Contact::getActive())
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->createOptionForm(fn (Schema $schema) => ContactForm::configure($schema))
                                    ->createOptionUsing(fn (array $data) => Contact::create($data)->getKey()),

                                TextInput::make('signing_place')
                                    ->label(__('app.label.signing_place'))
                                    ->maxLength(255)
                                    ->placeholder('г. Ташкент'),
                            ]),

                        Grid::make(['default' => 1, 'md' => 3])
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

                                DatePicker::make('deadline_at')
                                    ->label(__('app.label.deadline'))
                                    ->native(false)
                                    ->displayFormat('d.m.Y'),
                            ]),
                    ]),
            ]);
    }
}
