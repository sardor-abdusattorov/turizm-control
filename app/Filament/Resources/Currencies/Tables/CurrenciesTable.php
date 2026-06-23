<?php

namespace App\Filament\Resources\Currencies\Tables;

use App\Filament\Support\StatusToggleColumn;
use App\Models\Currency;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CurrenciesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->label(__('app.label.name'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('short_name')
                    ->label(__('app.label.currency_code'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('value')
                    ->label(__('app.label.currency_value'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('sort')
                    ->label(__('app.label.sort'))
                    ->sortable(),

                StatusToggleColumn::make()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label(__('app.label.created_at'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('app.label.status'))
                    ->options(Currency::getStatuses()),
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
