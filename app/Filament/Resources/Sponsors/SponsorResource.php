<?php

namespace App\Filament\Resources\Sponsors;

use App\Filament\Resources\Sponsors\Pages\CreateSponsor;
use App\Filament\Resources\Sponsors\Pages\EditSponsor;
use App\Filament\Resources\Sponsors\Pages\ListSponsors;
use App\Filament\Resources\Sponsors\Pages\ViewSponsor;
use App\Filament\Resources\Sponsors\Schemas\SponsorForm;
use App\Filament\Resources\Sponsors\Tables\SponsorsTable;
use App\Models\Sponsor;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SponsorResource extends Resource
{
    protected static ?string $model = Sponsor::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedStar;

    public static function getNavigationGroup(): ?string
    {
        return __('app.label.contacts');
    }

    public static function getModelLabel(): string
    {
        return __('app.label.sponsor_single');
    }

    public static function getPluralModelLabel(): string
    {
        return __('app.label.sponsors');
    }

    public static function getNavigationSort(): int
    {
        return 2;
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::$model::count();
    }

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return SponsorForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SponsorsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSponsors::route('/'),
            'create' => CreateSponsor::route('/create'),
            'view' => ViewSponsor::route('/{record}'),
            'edit' => EditSponsor::route('/{record}/edit'),
        ];
    }
}
