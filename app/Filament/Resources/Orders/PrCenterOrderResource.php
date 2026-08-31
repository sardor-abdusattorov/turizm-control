<?php

namespace App\Filament\Resources\Orders;

use App\Enums\OrderScope;
use App\Filament\Resources\Orders\Pages\CreatePrCenterOrder;
use App\Filament\Resources\Orders\Pages\EditPrCenterOrder;
use App\Filament\Resources\Orders\Pages\ListPrCenterOrders;
use App\Filament\Resources\Orders\Pages\ViewPrCenterOrder;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

class PrCenterOrderResource extends BaseOrderResource
{
    protected static ?string $slug = 'pr-center-orders';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    public static function orderScope(): OrderScope
    {
        return OrderScope::PrCenter;
    }

    public static function getNavigationLabel(): string
    {
        return __('app.label.pr_center_order_plural');
    }

    public static function getModelLabel(): string
    {
        return __('app.label.pr_center_order_single');
    }

    public static function getPluralModelLabel(): string
    {
        return __('app.label.pr_center_order_plural');
    }

    public static function getNavigationSort(): int
    {
        return 2;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPrCenterOrders::route('/'),
            'create' => CreatePrCenterOrder::route('/create'),
            'view' => ViewPrCenterOrder::route('/{record}'),
            'edit' => EditPrCenterOrder::route('/{record}/edit'),
        ];
    }
}
