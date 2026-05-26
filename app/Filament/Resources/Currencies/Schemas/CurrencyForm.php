<?php

namespace App\Filament\Resources\Currencies\Schemas;

use AbdulmajeedJamaan\FilamentTranslatableTabs\TranslatableTabs;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CurrencyForm
{
    public static function configure(Schema $schema): Schema
    {

        return $schema
            ->columns(1)
            ->components([
                Section::make(__('app.label.basic_information'))
                    ->schema([
                        TranslatableTabs::make()
                            ->schema([
                                TextInput::make('name')
                                    ->label(__('app.label.name'))
                                    ->required()
                                    ->maxLength(255),
                            ]),

                        TextInput::make('short_name')
                            ->label(__('app.label.currency_code'))
                            ->required()
                            ->maxLength(255),

                        TextInput::make('value')
                            ->label(__('app.label.currency_value'))
                            ->numeric()
                            ->inputMode('decimal')
                            ->step(0.01)
                            ->required(),

                        TextInput::make('sort')
                            ->label(__('app.label.sort'))
                            ->numeric()
                            ->default(0),

                        Toggle::make('status')
                            ->label(__('app.label.status'))
                            ->default(true),
                    ]),
            ]);
    }
}
