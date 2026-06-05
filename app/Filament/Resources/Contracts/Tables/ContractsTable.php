<?php

namespace App\Filament\Resources\Contracts\Tables;

use App\Filament\Pages\ContractDocumentEditor;
use App\Models\Contract;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ContractsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('number')
                    ->label(__('app.label.contract_number'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('title')
                    ->label(__('app.label.contract_title'))
                    ->searchable()
                    ->wrap()
                    ->limit(50),

                TextColumn::make('orderType.title')
                    ->label(__('app.label.order_type_single'))
                    ->badge()
                    ->toggleable(),

                TextColumn::make('contact.name')
                    ->label(__('app.label.contact_single'))
                    ->searchable()
                    ->limit(40),

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

                SelectFilter::make('status')
                    ->label(__('app.label.status'))
                    ->options(Contract::getStatuses()),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('openEditor')
                        ->label(__('app.action.open_editor'))
                        ->icon('heroicon-o-pencil-square')
                        ->color('primary')
                        ->url(fn (Contract $record) => ContractDocumentEditor::getUrl(['record' => $record]))
                        ->visible(fn (Contract $record) => $record->documentExists()),

                    EditAction::make(),

                    DeleteAction::make()
                        ->visible(fn (Contract $record) => $record->canBeDeletedBy()),
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
