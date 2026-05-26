<?php

namespace App\Filament\Resources\OrderTypes;

use App\Filament\Resources\OrderTypes\Pages\CreateOrderType;
use App\Filament\Resources\OrderTypes\Pages\EditOrderType;
use App\Filament\Resources\OrderTypes\Pages\ListOrderTypes;
use App\Filament\Resources\OrderTypes\Pages\ViewOrderType;
use App\Filament\Resources\OrderTypes\Schemas\OrderTypeForm;
use App\Filament\Resources\OrderTypes\Tables\OrderTypesTable;
use App\Models\OrderType;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class OrderTypeResource extends Resource
{
    protected static ?string $model = OrderType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    public static function getNavigationGroup(): ?string
    {
        return __('app.label.resources');
    }

    public static function getModelLabel(): string
    {
        return __('app.label.order_type_single');
    }

    public static function getPluralModelLabel(): string
    {
        return __('app.label.order_type_plural');
    }

    public static function getNavigationSort(): int
    {
        return 4;
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::$model::count();
    }

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return OrderTypeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OrderTypesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrderTypes::route('/'),
            'create' => CreateOrderType::route('/create'),
            'view' => ViewOrderType::route('/{record}'),
            'edit' => EditOrderType::route('/{record}/edit'),
        ];
    }
}
