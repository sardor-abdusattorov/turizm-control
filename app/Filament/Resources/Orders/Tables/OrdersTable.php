<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
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
            ->columns([
                TextColumn::make('title')
                    ->label(__('app.label.title'))
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                TextColumn::make('file_path')
                    ->label(__('app.label.document'))
                    ->badge()
                    ->color('primary')
                    ->icon('heroicon-o-document-text')
                    ->formatStateUsing(fn (?string $state): string => $state ? basename($state) : '—')
                    ->limit(35)
                    ->url(fn (Order $record) => $record->fileExists() && $record->isDocx()
                        ? route('orders.editor', ['order' => $record, 'mode' => 'edit'])
                        : null
                    ),

                TextColumn::make('orderType.title')
                    ->label(__('app.label.order_type_single'))
                    ->badge()
                    ->searchable()
                    ->sortable(),

                TextColumn::make('deadline_at')
                    ->label(__('app.label.deadline'))
                    ->date('d.m.Y')
                    ->sortable()
                    ->color(fn (?Order $record): ?string => match (true) {
                        $record?->deadline_at?->isPast() => 'danger',
                        $record?->deadline_at?->diffInDays(now(), false) >= -3 => 'warning',
                        default => null,
                    }),

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
                    ->offColor('danger'),

                TextColumn::make('created_at')
                    ->label(__('app.label.created_at'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('order_type_id')
                    ->label(__('app.label.order_type_single'))
                    ->relationship('orderType', 'title')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('status')
                    ->label(__('app.label.status'))
                    ->options(Order::getStatuses()),

                Filter::make('overdue')
                    ->label(__('app.label.overdue'))
                    ->query(fn (Builder $query) => $query->whereDate('deadline_at', '<', now())),
            ])
            ->recordUrl(fn (Order $record) => OrderResource::getUrl('view', ['record' => $record]))
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    DeleteAction::make(),
                ])
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->color('gray'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
