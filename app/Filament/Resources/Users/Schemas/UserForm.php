<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Filament\Support\ImageUpload;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('app.label.personal_info'))
                    ->schema([
                        TextInput::make('name')
                            ->label(__('app.label.name'))
                            ->required()
                            ->maxLength(255),

                        TextInput::make('email')
                            ->label(__('app.label.email'))
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),

                        TextInput::make('telegram_chat_id')
                            ->label(__('app.label.telegram_chat_id'))
                            ->maxLength(255),

                        ImageUpload::make('users'),
                    ])
                    ->columns(1),

                Section::make(__('app.label.work_info'))
                    ->schema([
                        Select::make('department_id')
                            ->label(__('app.label.department'))
                            ->relationship('department', 'name')
                            ->searchable()
                            ->preload(),

                        Select::make('position_id')
                            ->label(__('app.label.position'))
                            ->relationship('position', 'name')
                            ->searchable()
                            ->preload(),

                        Toggle::make('status')
                            ->label(__('app.label.status'))
                            ->default(true)
                            ->inline(false),
                    ])
                    ->columns(1),

                Section::make(__('app.label.security'))
                    ->schema([
                        TextInput::make('password')
                            ->label(__('app.label.password'))
                            ->password()
                            ->confirmed()
                            ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                            ->dehydrated(fn ($state) => filled($state))
                            ->required(fn (string $context): bool => $context === 'create')
                            ->maxLength(255),

                        TextInput::make('password_confirmation')
                            ->label(__('app.label.password_confirmation'))
                            ->password()
                            ->required(fn (string $context): bool => $context === 'create')
                            ->dehydrated(false),

                        Select::make('roles')
                            ->label(__('app.label.roles'))
                            ->searchable()
                            ->preload()
                            ->multiple()
                            ->relationship('roles', 'name'),
                    ])
                    ->columns(1),
            ]);
    }
}
