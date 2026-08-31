<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Filament\Support\ImageUpload;
use App\Models\Department;
use App\Models\Position;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
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
                            ->label(__('app.label.full_name'))
                            ->required()
                            ->maxLength(255),

                        TextInput::make('email')
                            ->label(__('app.label.email'))
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),

                        ImageUpload::make('users', 'avatar_url')
                            ->label(__('app.label.profile_image')),
                    ])
                    ->columns(1),

                Section::make(__('app.label.work_info'))
                    ->schema([
                        Select::make('department_id')
                            ->label(__('app.label.department'))
                            ->options(Department::getActive())
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('position_id', null)),

                        Select::make('position_id')
                            ->label(__('app.label.position'))
                            ->options(fn (Get $get): array => self::positionOptions($get('department_id')))
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
                            ->visible(fn (string $context): bool => $context !== 'view')
                            ->maxLength(255),

                        TextInput::make('password_confirmation')
                            ->label(__('app.label.password_confirmation'))
                            ->password()
                            ->required(fn (string $context): bool => $context === 'create')
                            ->visible(fn (string $context): bool => $context !== 'view')
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

    /** @return array<int, string> */
    private static function positionOptions(mixed $departmentId): array
    {
        if (! $departmentId) {
            return Position::getActive();
        }

        return Position::query()
            ->active()
            ->whereHas('departments', fn (Builder $query) => $query->whereKey($departmentId))
            ->orderBy('sort')
            ->get()
            ->mapWithKeys(fn (Position $position) => [
                $position->id => $position->getTranslation('name', app()->getLocale()),
            ])
            ->all();
    }
}
