<?php

namespace App\Filament\Resources\Departments\Tables;

use App\Filament\Support\CreatedAtColumn;
use App\Filament\Support\StatusToggleColumn;
use App\Filament\Support\UpdatedAtColumn;
use App\Models\Department;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DepartmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table

            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['head', 'positions']))
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->label(__('app.label.department_name'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('head.name')
                    ->label(__('app.label.department_chief'))
                    ->searchable()
                    ->sortable()
                    ->default(__('app.label.not_assigned')),

                TextColumn::make('positions.name')
                    ->label(__('app.label.position_plural'))
                    ->badge()
                    ->wrap()
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
                    ->options(Department::getStatuses()),

                SelectFilter::make('head_of_department')
                    ->label(__('app.label.department_chief'))
                    ->relationship('head', 'name')
                    ->searchable()
                    ->preload(),
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
                        ->visible(fn (): bool => auth()->user()?->can('delete_department') ?? false),
                ]),
            ]);
    }
}
