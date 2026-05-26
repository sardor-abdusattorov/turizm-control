<?php

namespace App\Filament\Resources\OrderTypes\Schemas;

use AbdulmajeedJamaan\FilamentTranslatableTabs\TranslatableTabs;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrderTypeForm
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
                                TextInput::make('title')
                                    ->label(__('app.label.title'))
                                    ->required()
                                    ->maxLength(255),

                                Textarea::make('description')
                                    ->label(__('app.label.description'))
                                    ->rows(3),
                            ]),

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
