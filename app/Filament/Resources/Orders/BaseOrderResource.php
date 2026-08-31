<?php

namespace App\Filament\Resources\Orders;

use App\Enums\OrderScope;
use App\Filament\Resources\Orders\Schemas\OrderForm;
use App\Filament\Resources\Orders\Tables\OrdersTable;
use App\Models\Order;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

abstract class BaseOrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $recordTitleAttribute = 'title';

    abstract public static function orderScope(): OrderScope;

    public static function getNavigationGroup(): ?string
    {
        return __('app.label.documents');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('scope', static::orderScope());
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) Order::query()->where('scope', static::orderScope())->count();
    }

    /** @return class-string<self> */
    public static function resourceFor(Order $record): string
    {
        return $record->scope === OrderScope::Committee
            ? CommitteeOrderResource::class
            : PrCenterOrderResource::class;
    }

    public static function urlFor(Order $record): string
    {
        return static::resourceFor($record)::getUrl('view', ['record' => $record]);
    }

    public static function form(Schema $schema): Schema
    {
        return OrderForm::configure($schema, static::orderScope());
    }

    public static function table(Table $table): Table
    {
        return OrdersTable::configure($table, static::orderScope());
    }

    public static function getRelations(): array
    {
        return [];
    }
}
