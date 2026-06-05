<?php

namespace App\Filament\Resources\Contracts\RelationManagers;

use App\Models\ContractApprover;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ApproversRelationManager extends RelationManager
{
    protected static string $relationship = 'approvers';

    protected static ?string $title = null;

    public static function getTitle($ownerRecord, string $pageClass): string
    {
        return __('app.label.approval_chain');
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->defaultSort('order')
            ->columns([
                TextColumn::make('order')
                    ->label('№')
                    ->sortable(),

                TextColumn::make('user.name')
                    ->label(__('app.label.full_name'))
                    ->searchable(),

                TextColumn::make('user.department.name')
                    ->label(__('app.label.department_single'))
                    ->badge(),

                TextColumn::make('user.position.name')
                    ->label(__('app.label.position_single'))
                    ->toggleable(),

                TextColumn::make('status')
                    ->label(__('app.label.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ContractApprover::getStatuses()[$state] ?? $state)
                    ->color(fn (string $state): string => ContractApprover::getStatusColors()[$state] ?? 'gray'),

                TextColumn::make('acted_at')
                    ->label(__('app.label.acted_at'))
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('—'),

                TextColumn::make('comment')
                    ->label(__('app.label.comment'))
                    ->wrap()
                    ->limit(60)
                    ->placeholder('—'),
            ])
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([])
            ->paginated(false);
    }
}
