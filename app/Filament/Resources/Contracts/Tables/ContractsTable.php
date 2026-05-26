<?php

namespace App\Filament\Resources\Contracts\Tables;

use App\Models\Contract;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ContractsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->recordClasses(fn (Contract $record): ?string => match (true) {
                $record->deadline_at?->isPast() => 'bg-red-50 dark:bg-red-900/20',
                $record->deadline_at?->diffInDays(now(), false) >= -3 => 'bg-yellow-50 dark:bg-yellow-900/20',
                default => null,
            })
            ->columns([
                TextColumn::make('number')
                    ->label(__('app.label.contract_number'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('title')
                    ->label(__('app.label.title'))
                    ->searchable()
                    ->wrap()
                    ->limit(60),

                TextColumn::make('orderType.title')
                    ->label(__('app.label.order_type_single'))
                    ->badge()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('client.name')
                    ->label(__('app.label.client_single'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('amount')
                    ->label(__('app.label.amount'))
                    ->money(fn (Contract $record) => $record->currency?->short_name ?? 'UZS', divideBy: 1)
                    ->sortable(),

                TextColumn::make('status')
                    ->label(__('app.label.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => Contract::getStatuses()[$state] ?? $state)
                    ->color(fn (string $state) => Contract::getStatusColors()[$state] ?? 'gray')
                    ->sortable(),

                TextColumn::make('deadline_at')
                    ->label(__('app.label.deadline'))
                    ->date('d.m.Y')
                    ->sortable(),

                TextColumn::make('responsible.name')
                    ->label(__('app.label.responsible'))
                    ->toggleable(),

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

                SelectFilter::make('client_id')
                    ->label(__('app.label.client_single'))
                    ->relationship('client', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('status')
                    ->label(__('app.label.status'))
                    ->options(Contract::getStatuses()),

                Filter::make('overdue')
                    ->label(__('app.label.overdue'))
                    ->query(fn (Builder $query) => $query->whereDate('deadline_at', '<', now())),
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
