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
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('issued_at', 'desc')
            ->columns([
                TextColumn::make('number')
                    ->label(__('app.label.order_number'))
                    ->searchable()
                    ->sortable()
                    ->weight('semibold'),

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
                    ->formatStateUsing(fn (?string $state): string => $state ? basename($state) : '—')
                    ->limit(35)
                    ->url(fn (Order $record): ?string => match (true) {
                        ! $record->fileExists() => null,
                        $record->isOpenableInOnlyOffice() => route('orders.editor', ['order' => $record, 'mode' => 'view']),
                        default => route('orders.file.inline', ['order' => $record]),
                    }, shouldOpenInNewTab: true),

                TextColumn::make('orderType.title')
                    ->label(__('app.label.order_type_single'))
                    ->badge()
                    ->searchable()
                    ->sortable(),

                TextColumn::make('issued_at')
                    ->label(__('app.label.issued_at'))
                    ->date('d.m.Y')
                    ->sortable(),

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
            ])
            ->recordUrl(fn (Order $record) => OrderResource::getUrl('view', ['record' => $record]))
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
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
