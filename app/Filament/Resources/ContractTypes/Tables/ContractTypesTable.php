<?php

namespace App\Filament\Resources\ContractTypes\Tables;

use App\Enums\ContractDirection;
use App\Filament\Support\CreatedAtColumn;
use App\Filament\Support\StatusToggleColumn;
use App\Models\ContractType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ContractTypesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->columns([
                TextColumn::make('title')
                    ->label(__('app.label.title'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('direction')
                    ->label(__('app.label.direction'))
                    ->badge()
                    ->formatStateUsing(fn (ContractDirection $state): string => $state->label())
                    ->color(fn (ContractDirection $state): string => $state->color())
                    ->sortable(),

                TextColumn::make('contracts_count')
                    ->label(__('app.label.contracts'))
                    ->counts('contracts')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('sort')
                    ->label(__('app.label.sort'))
                    ->sortable(),

                StatusToggleColumn::make()
                    ->sortable(),

                CreatedAtColumn::make(),
            ])
            ->filters([
                SelectFilter::make('direction')
                    ->label(__('app.label.direction'))
                    ->options(ContractDirection::options()),

                SelectFilter::make('status')
                    ->label(__('app.label.status'))
                    ->options(ContractType::getStatuses()),
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
                        ->visible(fn (): bool => auth()->user()?->can('delete_contract_type') ?? false),
                ]),
            ]);
    }
}
