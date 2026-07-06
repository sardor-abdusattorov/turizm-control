<?php

namespace App\Filament\Resources\Sponsors\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SponsorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('app.label.basic_information'))
                    ->schema([
                        TextInput::make('name')
                            ->label(__('app.label.name'))
                            ->required()
                            ->maxLength(255),

                        Grid::make(['default' => 1, 'md' => 3])
                            ->schema([
                                TextInput::make('phone')
                                    ->label(__('app.label.phone'))
                                    ->tel()
                                    ->maxLength(30),

                                TextInput::make('email')
                                    ->label(__('app.label.email'))
                                    ->email()
                                    ->maxLength(255),

                                TextInput::make('website')
                                    ->label(__('app.label.website'))
                                    ->url()
                                    ->maxLength(255),
                            ]),

                        Textarea::make('description')
                            ->label(__('app.label.description'))
                            ->rows(3),

                        Toggle::make('status')
                            ->label(__('app.label.status'))
                            ->default(true),
                    ]),
            ]);
    }
}
