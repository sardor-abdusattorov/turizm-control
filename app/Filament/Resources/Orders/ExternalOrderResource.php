<?php

namespace App\Filament\Resources\Orders;

use App\Enums\OrderScope;
use App\Filament\Resources\Orders\Pages\CreateExternalOrder;
use App\Filament\Resources\Orders\Pages\EditExternalOrder;
use App\Filament\Resources\Orders\Pages\ListExternalOrders;
use App\Filament\Resources\Orders\Pages\ViewExternalOrder;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

class ExternalOrderResource extends BaseOrderResource
{
    protected static ?string $slug = 'external-orders';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    public static function orderScope(): OrderScope
    {
        return OrderScope::External;
    }

    public static function getNavigationLabel(): string
    {
        return __('app.label.external_order_plural');
    }

    public static function getModelLabel(): string
    {
        return __('app.label.external_order_single');
    }

    public static function getPluralModelLabel(): string
    {
        return __('app.label.external_order_plural');
    }

    public static function getNavigationSort(): int
    {
        return 2;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListExternalOrders::route('/'),
            'create' => CreateExternalOrder::route('/create'),
            'view' => ViewExternalOrder::route('/{record}'),
            'edit' => EditExternalOrder::route('/{record}/edit'),
        ];
    }
}
