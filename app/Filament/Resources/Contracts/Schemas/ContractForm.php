<?php

namespace App\Filament\Resources\Contracts\Schemas;

use AbdulmajeedJamaan\FilamentTranslatableTabs\TranslatableTabs;
use App\Filament\Resources\Contacts\Schemas\ContactForm;
use App\Models\Contact;
use App\Models\Contract;
use App\Models\Currency;
use App\Models\OrderType;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
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
                        Grid::make(2)
                            ->schema([
                                Select::make('order_type_id')
                                    ->label(__('app.label.order_type_single'))
                                    ->options(OrderType::getActive())
                                    ->required()
                                    ->searchable()
                                    ->preload(),

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

                        Grid::make(3)
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

                        Select::make('status')
                            ->label(__('app.label.status'))
                            ->options(Contract::getStatuses())
                            ->default(Contract::STATUS_DRAFT)
                            ->required()
                            ->native(false),
                    ]),
            ]);
    }
}
