<?php

namespace App\Filament\Resources\Contracts\RelationManagers;

use App\Models\ContractApprover;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
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
                Split::make([
                    TextColumn::make('order')
                        ->label('#')
                        ->size('lg')
                        ->weight('bold')
                        ->color('gray')
                        ->grow(false),

                    ImageColumn::make('user.avatar_url')
                        ->label('')
                        ->circular()
                        ->defaultImageUrl(fn ($record) => 'https://ui-avatars.com/api/?name='
                            .urlencode($record->user?->name ?? '?')
                            .'&background=E0E7FF&color=4338CA&size=64')
                        ->grow(false),

                    Stack::make([
                        TextColumn::make('user.name')
                            ->label(__('app.label.full_name'))
                            ->weight('bold')
                            ->searchable(),

                        TextColumn::make('user.department.name')
                            ->label(__('app.label.department_single'))
                            ->size('sm')
                            ->color('gray'),
                    ]),

                    TextColumn::make('comment')
                        ->label(__('app.label.comment'))
                        ->wrap()
                        ->limit(60)
                        ->placeholder('—'),

                    Stack::make([
                        TextColumn::make('status')
                            ->label(__('app.label.status'))
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => ContractApprover::getStatuses()[$state] ?? $state)
                            ->color(fn (string $state): string => ContractApprover::getStatusColors()[$state] ?? 'gray'),

                        TextColumn::make('acted_at')
                            ->label(__('app.label.acted_at'))
                            ->dateTime('d.m.Y H:i')
                            ->size('sm')
                            ->color('gray')
                            ->placeholder('—')
                            ->visible(fn (ContractApprover $record): bool => $record->acted_at !== null),

                        TextColumn::make('due_at')
                            ->label(__('app.label.due_at'))
                            ->state(fn (ContractApprover $record): ?string => $record->isPending() && $record->due_at
                                ? '⏳ '.$record->due_at->format('d.m.Y H:i')
                                : null)
                            ->color(fn (ContractApprover $record): string => $record->isOverdue() ? 'danger' : 'gray')
                            ->size('sm')
                            ->placeholder(''),
                    ])->alignment('end'),
                ]),
            ])
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([])
            ->paginated(false);
    }
}
