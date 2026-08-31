<?php

namespace App\Filament\Resources\Orders;

use App\Enums\OrderScope;
use App\Filament\Resources\Orders\Pages\CreateCommitteeOrder;
use App\Filament\Resources\Orders\Pages\EditCommitteeOrder;
use App\Filament\Resources\Orders\Pages\ListCommitteeOrders;
use App\Filament\Resources\Orders\Pages\ViewCommitteeOrder;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

class CommitteeOrderResource extends BaseOrderResource
{
    protected static ?string $slug = 'committee-orders';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    public static function orderScope(): OrderScope
    {
        return OrderScope::Committee;
    }

    public static function getNavigationLabel(): string
    {
        return __('app.label.committee_order_plural');
    }

    public static function getModelLabel(): string
    {
        return __('app.label.committee_order_single');
    }

    public static function getPluralModelLabel(): string
    {
        return __('app.label.committee_order_plural');
    }

    public static function getNavigationSort(): int
    {
        return 1;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCommitteeOrders::route('/'),
            'create' => CreateCommitteeOrder::route('/create'),
            'view' => ViewCommitteeOrder::route('/{record}'),
            'edit' => EditCommitteeOrder::route('/{record}/edit'),
        ];
    }
}
