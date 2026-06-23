<?php

namespace App\Filament\Resources\Positions\Tables;

use App\Filament\Support\StatusToggleColumn;
use App\Models\Position;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PositionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('app.label.position_name'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('departments.name')
                    ->label(__('app.label.department_plural'))
                    ->badge()
                    ->searchable(),

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
                    ->options(Position::getStatuses()),

                SelectFilter::make('departments')
                    ->label(__('app.label.department_plural'))
                    ->relationship('departments', 'name')
                    ->searchable()
                    ->preload()
                    ->multiple(),
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
