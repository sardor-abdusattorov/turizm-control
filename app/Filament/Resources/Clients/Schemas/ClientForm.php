<?php

namespace App\Filament\Resources\Clients\Schemas;

use AbdulmajeedJamaan\FilamentTranslatableTabs\TranslatableTabs;
use App\Models\Client;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ClientForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('app.label.basic_information'))
                    ->schema([
                        Radio::make('type')
                            ->label(__('app.label.client_type'))
                            ->options(Client::getTypes())
                            ->default(Client::TYPE_LEGAL)
                            ->required()
                            ->inline()
                            ->live(),

                        TranslatableTabs::make()
                            ->schema([
                                TextInput::make('name')
                                    ->label(fn (Get $get) => $get('type') === Client::TYPE_INDIVIDUAL
                                        ? __('app.label.full_name')
                                        : __('app.label.client_name'))
                                    ->required()
                                    ->maxLength(255),

                                Textarea::make('address')
                                    ->label(__('app.label.address'))
                                    ->rows(2),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('inn')
                                    ->label(fn (Get $get) => $get('type') === Client::TYPE_INDIVIDUAL
                                        ? __('app.label.pinfl')
                                        : __('app.label.inn'))
                                    ->required(fn (Get $get) => $get('type') === Client::TYPE_LEGAL)
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(30),

                                TextInput::make('contact_person')
                                    ->label(__('app.label.contact_person'))
                                    ->visible(fn (Get $get) => $get('type') === Client::TYPE_LEGAL)
                                    ->maxLength(255),

                                TextInput::make('phone')
                                    ->label(__('app.label.phone'))
                                    ->tel()
                                    ->maxLength(50),

                                TextInput::make('email')
                                    ->label(__('app.label.email'))
                                    ->email()
                                    ->maxLength(255),
                            ]),

                        Toggle::make('status')
                            ->label(__('app.label.status'))
                            ->default(true),
                    ]),
            ]);
    }
}
