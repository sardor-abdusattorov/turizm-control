<?php

namespace App\Filament\Resources\Orders;

use App\Enums\OrderScope;
use App\Filament\Resources\Orders\Pages\CreateInternalOrder;
use App\Filament\Resources\Orders\Pages\EditInternalOrder;
use App\Filament\Resources\Orders\Pages\ListInternalOrders;
use App\Filament\Resources\Orders\Pages\ViewInternalOrder;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

class InternalOrderResource extends BaseOrderResource
{
    protected static ?string $slug = 'internal-orders';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    public static function orderScope(): OrderScope
    {
        return OrderScope::Internal;
    }

    public static function getNavigationLabel(): string
    {
        return __('app.label.internal_order_plural');
    }

    public static function getModelLabel(): string
    {
        return __('app.label.internal_order_single');
    }

    public static function getPluralModelLabel(): string
    {
        return __('app.label.internal_order_plural');
    }

    public static function getNavigationSort(): int
    {
        return 1;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInternalOrders::route('/'),
            'create' => CreateInternalOrder::route('/create'),
            'view' => ViewInternalOrder::route('/{record}'),
            'edit' => EditInternalOrder::route('/{record}/edit'),
        ];
    }
}
