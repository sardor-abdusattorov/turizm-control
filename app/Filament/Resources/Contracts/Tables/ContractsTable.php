<?php

namespace App\Filament\Resources\Contracts\Tables;

use App\Filament\Resources\Contracts\ContractResource;
use App\Models\Contract;
use App\Services\Contracts\ContractWorkflow;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
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
                SelectFilter::make('status')
                    ->label(__('app.label.status'))
                    ->options(Contract::getStatuses()),
            ])
            ->recordUrl(fn (Contract $record) => ContractResource::getUrl('view', ['record' => $record]))
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),

                    Action::make('downloadPdf')
                        ->label(__('app.action.download_pdf'))
                        ->icon('heroicon-o-document-arrow-down')
                        ->url(fn (Contract $record) => route('contracts.pdf.download', ['contract' => $record]))
                        ->visible(fn (Contract $record) => $record->status === Contract::STATUS_APPROVED),

                    Action::make('previewPdf')
                        ->label(__('app.action.preview_pdf'))
                        ->icon('heroicon-o-eye')
                        ->url(
                            fn (Contract $record) => route('contracts.pdf.inline', ['contract' => $record]),
                            shouldOpenInNewTab: true,
                        )
                        ->visible(fn (Contract $record) => $record->documentExists() && in_array($record->status, [
                            Contract::STATUS_IN_REVIEW,
                            Contract::STATUS_APPROVED,
                        ], true)),

                    Action::make('openEditor')
                        ->label(__('app.action.open_editor'))
                        ->icon('heroicon-o-pencil-square')
                        ->url(fn (Contract $record) => route('contracts.editor', ['contract' => $record]))
                        ->visible(fn (Contract $record) => $record->documentExists() && $record->canBeEditedBy()),

                    Action::make('submitForApproval')
                        ->label(__('app.action.submit_for_approval'))
                        ->icon('heroicon-o-paper-airplane')
                        ->requiresConfirmation()
                        ->modalHeading(__('app.action.submit_for_approval'))
                        ->modalDescription(__('app.message.submit_for_approval_confirm'))
                        ->visible(fn (Contract $record) => $record->canBeSubmittedBy())
                        ->action(function (Contract $record, ContractWorkflow $workflow): void {
                            if (! $workflow->submit($record)) {
                                Notification::make()->title(__('app.message.action_not_allowed'))->danger()->send();

                                return;
                            }

                            Notification::make()
                                ->title(__('app.message.submitted_for_approval'))
                                ->success()
                                ->send();
                        }),

                    EditAction::make()
                        ->visible(fn (Contract $record) => $record->canBeEditedBy()),

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
