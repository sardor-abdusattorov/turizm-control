<?php

namespace App\Filament\Resources\Contacts\Schemas;

use AbdulmajeedJamaan\FilamentTranslatableTabs\TranslatableTabs;
use App\Models\Contact;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ContactForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('app.label.basic_information'))
                    ->schema([
                        Radio::make('type')
                            ->label(__('app.label.contact_type'))
                            ->options(Contact::getTypes())
                            ->default(Contact::TYPE_LEGAL)
                            ->required()
                            ->inline()
                            ->live(),

                        TranslatableTabs::make()
                            ->schema([
                                TextInput::make('name')
                                    ->label(__('app.label.contact_name'))
                                    ->required()
                                    ->maxLength(255),

                                Textarea::make('address')
                                    ->label(__('app.label.address'))
                                    ->rows(2),
                            ]),

                        Grid::make(['default' => 1, 'md' => 2])
                            ->schema([
                                TextInput::make('legal_form')
                                    ->label(__('app.label.legal_form'))
                                    ->visible(fn (Get $get) => $get('type') === Contact::TYPE_LEGAL)
                                    ->maxLength(50),

                                TextInput::make('oked')
                                    ->label(__('app.label.oked'))
                                    ->visible(fn (Get $get) => $get('type') === Contact::TYPE_LEGAL)
                                    ->maxLength(20),

                                TextInput::make('inn')
                                    ->label(__('app.label.inn'))
                                    ->visible(fn (Get $get) => $get('type') === Contact::TYPE_LEGAL)
                                    ->required(fn (Get $get) => $get('type') === Contact::TYPE_LEGAL)
                                    ->unique('contacts', 'inn', ignoreRecord: true)
                                    ->maxLength(30),

                                TextInput::make('pinfl')
                                    ->label(__('app.label.pinfl'))
                                    ->visible(fn (Get $get) => $get('type') === Contact::TYPE_INDIVIDUAL)
                                    ->required(fn (Get $get) => $get('type') === Contact::TYPE_INDIVIDUAL)
                                    ->unique('contacts', 'pinfl', ignoreRecord: true)
                                    ->maxLength(30),

                                TextInput::make('director_name')
                                    ->label(__('app.label.director_name'))
                                    ->visible(fn (Get $get) => $get('type') === Contact::TYPE_LEGAL)
                                    ->maxLength(255),

                                TextInput::make('contact_person')
                                    ->label(__('app.label.contact_person'))
                                    ->visible(fn (Get $get) => $get('type') === Contact::TYPE_LEGAL)
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

                Section::make(__('app.label.bank_requisites'))
                    ->visible(fn (Get $get) => $get('type') === Contact::TYPE_LEGAL)
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 2])
                            ->schema([
                                TextInput::make('bank_account')
                                    ->label(__('app.label.bank_account'))
                                    ->maxLength(50),

                                TextInput::make('mfo')
                                    ->label(__('app.label.mfo'))
                                    ->maxLength(20),

                                TextInput::make('bank_name')
                                    ->label(__('app.label.bank_name'))
                                    ->columnSpanFull()
                                    ->maxLength(255),
                            ]),
                    ]),
            ]);
    }
}
