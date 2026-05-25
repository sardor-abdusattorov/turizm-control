<?php

namespace App\Filament\Resources\Departments\Schemas;

use AbdulmajeedJamaan\FilamentTranslatableTabs\TranslatableTabs;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DepartmentForm
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
                                    ->label(__('app.label.department_name'))
                                    ->required()
                                    ->maxLength(255),
                            ]),

                        Select::make('head_of_department')
                            ->label(__('app.label.department_chief'))
                            ->relationship('head', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable(),

                        Select::make('positions')
                            ->label(__('app.label.position_plural'))
                            ->relationship('positions', 'name')
                            ->multiple()
                            ->searchable()
                            ->preload(),

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
