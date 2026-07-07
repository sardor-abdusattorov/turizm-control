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

/**
 * Internal and external orders are two full-fledged sidebar resources over
 * the same Order model — the same split the project resources use. Each
 * subclass pins its OrderScope, scopes its queries and stamps it on create.
 * Shield keys permissions by model, so both share the *_order set.
 */
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

    /**
     * The concrete resource a record belongs to — for links rendered in
     * shared contexts (editor back-link, project view page).
     *
     * @return class-string<self>
     */
    public static function resourceFor(Order $record): string
    {
        return $record->scope === OrderScope::External
            ? ExternalOrderResource::class
            : InternalOrderResource::class;
    }

    public static function form(Schema $schema): Schema
    {
        return OrderForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OrdersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }
}
