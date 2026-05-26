<?php

namespace App\Filament\Resources\Contracts\Tables;

use App\Models\Contract;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
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
                $record->status === Contract::STATUS_APPROVED, $record->status === Contract::STATUS_ARCHIVED => null,
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

                TextColumn::make('contact.name')
                    ->label(__('app.label.contact_single'))
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

                SelectFilter::make('contact_id')
                    ->label(__('app.label.contact_single'))
                    ->relationship('contact', 'name')
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
                ActionGroup::make([
                    Action::make('submitForApproval')
                        ->label(__('app.action.submit_for_approval'))
                        ->icon('heroicon-o-paper-airplane')
                        ->color('primary')
                        ->visible(fn (Contract $record) => $record->canBeSubmittedBy())
                        ->requiresConfirmation()
                        ->modalHeading(__('app.action.submit_for_approval'))
                        ->modalDescription(__('app.message.submit_for_approval_confirm'))
                        ->action(function (Contract $record) {
                            $record->update(['status' => Contract::STATUS_IN_REVIEW]);

                            Notification::make()
                                ->title(__('app.message.contract_submitted'))
                                ->success()
                                ->send();
                        }),

                    Action::make('approve')
                        ->label(__('app.action.approve'))
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn (Contract $record) => $record->canBeApprovedBy())
                        ->modalHeading(__('app.action.approve'))
                        ->schema([
                            Textarea::make('comment')
                                ->label(__('app.label.comment'))
                                ->rows(3),
                        ])
                        ->action(function (array $data, Contract $record) {
                            $current = $record->currentApprover();
                            $current?->markApproved($data['comment'] ?? null);

                            if ($record->allApproved()) {
                                $record->update([
                                    'status' => Contract::STATUS_APPROVED,
                                    'signed_at' => now()->toDateString(),
                                ]);
                            }

                            Notification::make()
                                ->title(__('app.message.contract_approved'))
                                ->success()
                                ->send();
                        }),

                    Action::make('reject')
                        ->label(__('app.action.reject'))
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->visible(fn (Contract $record) => $record->canBeApprovedBy())
                        ->modalHeading(__('app.action.reject'))
                        ->schema([
                            Textarea::make('comment')
                                ->label(__('app.label.rejection_reason'))
                                ->required()
                                ->rows(3),
                        ])
                        ->action(function (array $data, Contract $record) {
                            $current = $record->currentApprover();
                            $current?->markRejected($data['comment']);

                            $record->update(['status' => Contract::STATUS_REJECTED]);

                            Notification::make()
                                ->title(__('app.message.contract_rejected'))
                                ->danger()
                                ->send();
                        }),

                    Action::make('returnForRevision')
                        ->label(__('app.action.return_for_revision'))
                        ->icon('heroicon-o-arrow-uturn-left')
                        ->color('warning')
                        ->visible(fn (Contract $record) => $record->canBeApprovedBy())
                        ->modalHeading(__('app.action.return_for_revision'))
                        ->schema([
                            Textarea::make('comment')
                                ->label(__('app.label.return_reason'))
                                ->required()
                                ->rows(3),
                        ])
                        ->action(function (array $data, Contract $record) {
                            $current = $record->currentApprover();
                            $current?->update([
                                'comment' => $data['comment'],
                                'acted_at' => now(),
                            ]);

                            $record->update(['status' => Contract::STATUS_DRAFT]);

                            Notification::make()
                                ->title(__('app.message.contract_returned'))
                                ->warning()
                                ->send();
                        }),

                    Action::make('archive')
                        ->label(__('app.action.archive'))
                        ->icon('heroicon-o-archive-box')
                        ->color('gray')
                        ->visible(fn (Contract $record) => $record->canBeArchivedBy())
                        ->requiresConfirmation()
                        ->action(function (Contract $record) {
                            $record->update(['status' => Contract::STATUS_ARCHIVED]);

                            Notification::make()
                                ->title(__('app.message.contract_archived'))
                                ->success()
                                ->send();
                        }),

                    ViewAction::make(),

                    EditAction::make()
                        ->visible(fn (Contract $record) => $record->canBeEditedBy()),

                    DeleteAction::make()
                        ->visible(fn (Contract $record) => $record->canBeDeletedBy()),
                ])
                    ->label(__('app.label.actions'))
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
