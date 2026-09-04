<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Enums\OrderScope;
use App\Filament\Resources\Orders\BaseOrderResource;
use App\Filament\Support\CreatedAtColumn;
use App\Filament\Support\StatusToggleColumn;
use App\Filament\Support\UpdatedAtColumn;
use App\Models\Order;
use App\Models\User;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OrdersTable
{
    public static function configure(Table $table, ?OrderScope $scope = null): Table
    {
        $isPrCenter = $scope === OrderScope::PrCenter;

        return $table

            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with(['creator', 'basisOrder'])
                ->withCount('derivedOrders'))
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('number')
                    ->label(__('app.label.order_number'))
                    ->searchable()
                    ->sortable()
                    ->weight('semibold'),

                TextColumn::make('basisOrder.number')
                    ->label(__('app.label.committee_order_basis'))
                    ->badge()
                    ->color('success')
                    ->icon('heroicon-o-building-library')
                    ->placeholder(__('app.label.not_set'))
                    ->description(fn (Order $record): ?string => $record->basisOrder?->title)
                    ->url(fn (Order $record): ?string => $record->basisOrder
                        ? BaseOrderResource::urlFor($record->basisOrder)
                        : null)
                    ->visible($isPrCenter)
                    ->toggleable(),

                TextColumn::make('derived_orders_count')
                    ->label(__('app.label.pr_center_order_plural'))
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'info' : 'gray')
                    ->icon('heroicon-o-document-text')
                    ->formatStateUsing(fn (int $state): string => (string) $state)
                    ->visible(! $isPrCenter)
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('title')
                    ->label(__('app.label.title'))
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                TextColumn::make('file_path')
                    ->label(__('app.label.document'))
                    ->badge()
                    ->color(fn (Order $record): string => $record->documentColor())
                    ->icon(fn (Order $record): string => $record->documentIcon())
                    ->formatStateUsing(fn (?string $state): string => $state ? basename($state) : __('app.label.not_set'))
                    ->limit(35)
                    ->url(fn (Order $record): ?string => $record->fileExists()
                        ? route('orders.file.inline', ['order' => $record])
                        : null, shouldOpenInNewTab: true),

                TextColumn::make('issued_at')
                    ->label(__('app.label.issued_at'))
                    ->date('d.m.Y')
                    ->sortable(),

                TextColumn::make('creator.name')
                    ->label(__('app.label.created_by'))
                    ->sortable()
                    ->toggleable(),

                StatusToggleColumn::make()
                    ->sortable(),

                CreatedAtColumn::make()
                    ->toggleable(isToggledHiddenByDefault: true),

                UpdatedAtColumn::make(),
            ])
            ->filters([
                SelectFilter::make('issued_year')
                    ->label(__('app.label.year'))
                    ->options(fn (): array => Order::query()
                        ->whereNotNull('issued_at')
                        ->pluck('issued_at')
                        ->map(fn ($d) => $d?->year)
                        ->filter()
                        ->unique()
                        ->sortDesc()
                        ->mapWithKeys(fn ($y) => [$y => $y])
                        ->all())
                    ->query(fn (Builder $query, array $data) => $data['value']
                        ? $query->whereYear('issued_at', $data['value'])
                        : $query),

                SelectFilter::make('status')
                    ->label(__('app.label.status'))
                    ->options(Order::getStatuses()),

                SelectFilter::make('created_by')
                    ->label(__('app.label.created_by'))
                    ->options(fn () => User::query()
                        ->where('status', User::STATUS_ACTIVE)
                        ->orderBy('name')
                        ->pluck('name', 'id'))
                    ->searchable(),
            ])
            ->filtersFormColumns(2)
            ->recordUrl(fn (Order $record): string => BaseOrderResource::urlFor($record))
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    DeleteAction::make(),
                ])
                    ->icon('heroicon-m-ellipsis-vertical'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn (): bool => auth()->user()?->can('delete_order') ?? false),
                ]),
            ]);
    }
}
