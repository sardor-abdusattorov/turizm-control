<?php

namespace App\Filament\Resources\Positions\Tables;

use App\Filament\Support\CreatedAtColumn;
use App\Filament\Support\StatusToggleColumn;
use App\Filament\Support\UpdatedAtColumn;
use App\Models\Position;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PositionsTable
{
    public static function configure(Table $table): Table
    {
        return $table

            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['departments']))
            ->defaultSort('updated_at', 'desc')
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

                CreatedAtColumn::make(),

                UpdatedAtColumn::make(),
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
            ->filtersFormColumns(2)
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn (): bool => auth()->user()?->can('delete_position') ?? false),
                ]),
            ]);
    }
}
