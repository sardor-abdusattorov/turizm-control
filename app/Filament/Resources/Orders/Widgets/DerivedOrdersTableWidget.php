<?php

namespace App\Filament\Resources\Orders\Widgets;

use App\Enums\OrderScope;
use App\Filament\Resources\Orders\BaseOrderResource;
use App\Models\Order;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * The PR-centre buyruqs issued on the strength of a committee one — the second
 * half of the chain, read-only, on the committee order's view page.
 */
class DerivedOrdersTableWidget extends TableWidget
{
    public int $orderId;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading(null)
            ->columnManager(false)
            ->query(fn (): Builder => Order::query()
                ->where('basis_order_id', $this->orderId)
                ->where('scope', OrderScope::PrCenter)
                ->with('creator')
                ->orderByDesc('issued_at')
                ->orderByDesc('id'))
            ->columns([
                TextColumn::make('number')
                    ->label(__('app.label.order_number'))
                    ->badge()
                    ->color('info')
                    ->icon('heroicon-o-document-text'),

                TextColumn::make('title')
                    ->label(__('app.label.title'))
                    ->wrap(),

                TextColumn::make('issued_at')
                    ->label(__('app.label.issued_at'))
                    ->date('d.m.Y')
                    ->placeholder(__('app.label.not_set')),

                TextColumn::make('status')
                    ->label(__('app.label.status'))
                    ->badge()
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray')
                    ->formatStateUsing(fn (bool $state): string => $state
                        ? __('app.status.active')
                        : __('app.status.inactive')),

                TextColumn::make('creator.name')
                    ->label(__('app.label.created_by'))
                    ->placeholder(__('app.label.system'))
                    ->toggleable(),
            ])
            ->recordActions([
                Action::make('open')
                    ->label(__('app.action.open'))
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn (Order $record): string => BaseOrderResource::urlFor($record)),
            ])
            ->paginated(false)
            ->emptyStateHeading(__('app.message.no_derived_orders'))
            ->emptyStateIcon('heroicon-o-document-text');
    }
}
