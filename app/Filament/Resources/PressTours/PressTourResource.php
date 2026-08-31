<?php

namespace App\Filament\Resources\PressTours;

use App\Filament\Resources\PressTours\Pages\CreatePressTour;
use App\Filament\Resources\PressTours\Pages\EditPressTour;
use App\Filament\Resources\PressTours\Pages\ListPressTours;
use App\Filament\Resources\PressTours\Pages\ViewPressTour;
use App\Filament\Resources\PressTours\Schemas\PressTourForm;
use App\Filament\Resources\PressTours\Tables\PressToursTable;
use App\Models\PressTour;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PressTourResource extends Resource
{
    protected static ?string $model = PressTour::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationGroup(): ?string
    {
        return __('app.label.projects');
    }

    public static function getModelLabel(): string
    {
        return __('app.label.press_tour_single');
    }

    public static function getPluralModelLabel(): string
    {
        return __('app.label.press_tour_plural');
    }

    public static function getNavigationSort(): int
    {
        return 3;
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getEloquentQuery()->count();
    }

    public static function form(Schema $schema): Schema
    {
        return PressTourForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PressToursTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPressTours::route('/'),
            'create' => CreatePressTour::route('/create'),
            'view' => ViewPressTour::route('/{record}'),
            'edit' => EditPressTour::route('/{record}/edit'),
        ];
    }
}
