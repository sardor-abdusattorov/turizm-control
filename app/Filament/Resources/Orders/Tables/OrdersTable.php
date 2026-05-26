<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Models\Order;
use App\Models\OrderType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->filtersLayout(FiltersLayout::Hidden)
            ->columns([
                TextColumn::make('title')
                    ->label(__('app.label.title'))
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                TextColumn::make('orderType.title')
                    ->label(__('app.label.order_type_single'))
                    ->badge()
                    ->sortable()
                    ->headerFilter(
                        SelectFilter::make('order_type_id')
                            ->options(fn () => OrderType::query()
                                ->orderBy('sort')
                                ->get()
                                ->mapWithKeys(fn (OrderType $t) => [
                                    $t->id => $t->getTranslation('title', app()->getLocale()),
                                ])
                                ->toArray())
                            ->native(false)
                    ),

                TextColumn::make('deadline_at')
                    ->label(__('app.label.deadline'))
                    ->date('d.m.Y')
                    ->sortable()
                    ->color(fn (?Order $record): ?string => match (true) {
                        $record?->deadline_at?->isPast() => 'danger',
                        $record?->deadline_at?->diffInDays(now(), false) >= -3 => 'warning',
                        default => null,
                    })
                    ->headerFilter(
                        Filter::make('deadline_at_range')
                            ->columns(2)
                            ->schema([
                                DatePicker::make('from')
                                    ->placeholder(__('app.label.from'))
                                    ->native(false),
                                DatePicker::make('until')
                                    ->placeholder(__('app.label.until'))
                                    ->native(false),
                            ])
                            ->query(fn (Builder $query, array $data): Builder => $query
                                ->when($data['from'] ?? null, fn (Builder $q, $v) => $q->whereDate('deadline_at', '>=', $v))
                                ->when($data['until'] ?? null, fn (Builder $q, $v) => $q->whereDate('deadline_at', '<=', $v)))
                    ),

                TextColumn::make('creator.name')
                    ->label(__('app.label.created_by'))
                    ->sortable()
                    ->toggleable(),

                ToggleColumn::make('status')
                    ->label(__('app.label.status'))
                    ->sortable()
                    ->onIcon('heroicon-m-check-circle')
                    ->offIcon('heroicon-m-x-circle')
                    ->onColor('success')
                    ->offColor('danger')
                    ->headerFilter(
                        SelectFilter::make('status')
                            ->options(Order::getStatuses())
                            ->native(false)
                    ),

                TextColumn::make('created_at')
                    ->label(__('app.label.created_at'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
