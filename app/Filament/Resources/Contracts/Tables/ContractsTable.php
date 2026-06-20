<?php

namespace App\Filament\Resources\Contracts\Tables;

use App\Enums\PaymentStatus;
use App\Exports\ContractsExport;
use App\Filament\Resources\Contracts\ContractResource;
use App\Filament\Resources\Contracts\Pages\ViewContract;
use App\Models\Contract;
use App\Models\Currency;
use App\Models\OrderType;
use App\Models\User;
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
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Maatwebsite\Excel\Facades\Excel;

class ContractsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                // Identity first (number), then state right after so the eye
                // catches "what is it / what shape is it in" before the details.
                TextColumn::make('number')
                    ->label(__('app.label.contract_number'))
                    ->weight('semibold')
                    ->searchable()
                    ->sortable(),

                ViewColumn::make('status')
                    ->label(__('app.label.status'))
                    ->view('filament.resources.contracts.tables.status-column')
                    ->sortable(),

                TextColumn::make('title')
                    ->label(__('app.label.contract_title'))
                    ->searchable()
                    ->wrap()
                    ->limit(50),

                TextColumn::make('contact.name')
                    ->label(__('app.label.contact_single'))
                    ->searchable()
                    ->limit(40)
                    ->toggleable(),

                TextColumn::make('amount')
                    ->label(__('app.label.amount'))
                    ->formatStateUsing(fn (?string $state, Contract $record): string => number_format((float) $state, 2, ',', ' ').' '.($record->currency?->short_name ?? ''))
                    ->sortable(),

                ViewColumn::make('approvers_chain')
                    ->label(__('app.label.approvers'))
                    ->view('filament.resources.contracts.tables.approvers-column')
                    ->disableClick()
                    ->extraHeaderAttributes(['style' => 'min-width:14rem;'])
                    ->extraAttributes(['style' => 'min-width:14rem;vertical-align:middle;']),

                TextColumn::make('responsible.name')
                    ->label(__('app.label.responsible'))
                    ->toggleable(),

                // Payment state as a colour pill (status + percent) in the same
                // visual family as the status column — visible by default so
                // payment progress reads at a glance.
                ViewColumn::make('payment_status')
                    ->label(__('app.label.payment_status'))
                    ->view('filament.resources.contracts.tables.payment-column')
                    ->sortable(),

                TextColumn::make('paid_percent')
                    ->label(__('app.label.paid_percent'))
                    ->formatStateUsing(fn ($state): string => rtrim(rtrim(number_format((float) $state, 2, '.', ''), '0'), '.').'%')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

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

                SelectFilter::make('responsible_id')
                    ->label(__('app.label.responsible'))
                    ->options(fn () => User::query()->where('status', User::STATUS_ACTIVE)->orderBy('name')->pluck('name', 'id'))
                    ->searchable(),

                SelectFilter::make('currency_id')
                    ->label(__('app.label.currency_single'))
                    ->options(fn () => Currency::query()->where('status', true)->orderBy('short_name')->pluck('short_name', 'id')),

                SelectFilter::make('order_type_id')
                    ->label(__('app.label.order_type_single'))
                    ->options(fn () => OrderType::getActive())
                    ->searchable(),

                SelectFilter::make('payment_status')
                    ->label(__('app.label.payment_status'))
                    ->options(PaymentStatus::options()),
            ])
            ->recordUrl(fn (Contract $record) => ContractResource::getUrl('view', ['record' => $record]))
            ->recordActions([
                Action::make('contractFlow')
                    ->hiddenLabel()
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('app.action.close'))
                    ->modalWidth('lg')
                    ->modalHeading(fn (Contract $record): string => trim(($record->number ? $record->number.' · ' : '').($record->title ?? '')) ?: __('app.label.approval_chain'))
                    ->modalContent(fn (Contract $record) => view(
                        'filament.resources.contracts.tables.approval-flow-modal',
                        ['contract' => $record],
                    ))
                    ->extraAttributes(['style' => 'display:none;']),

                ActionGroup::make([
                    ViewAction::make()
                        ->color('gray'),

                    Action::make('previewPdf')
                        ->label(__('app.action.preview_pdf'))
                        ->icon('heroicon-o-eye')
                        ->color('gray')
                        ->url(
                            fn (Contract $record) => route('contracts.pdf.inline', ['contract' => $record]),
                            shouldOpenInNewTab: true,
                        )
                        // PDF preview unlocks only once approved (after the director signs off).
                        ->visible(fn (Contract $record) => $record->documentExists()
                            && $record->status === Contract::STATUS_APPROVED),

                    Action::make('downloadPdf')
                        ->label(__('app.action.download_pdf'))
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('gray')
                        ->url(fn (Contract $record) => route('contracts.pdf.download', ['contract' => $record]))
                        ->visible(fn (Contract $record): bool => $record->status === Contract::STATUS_APPROVED
                            && ViewContract::userCanExportContract()),

                    Action::make('openEditor')
                        ->label(__('app.action.open_editor'))
                        ->icon('heroicon-o-pencil-square')
                        ->color('gray')
                        ->url(fn (Contract $record) => route('contracts.editor', [
                            'contract' => $record,
                            'mode' => 'edit',
                        ]))
                        ->visible(fn (Contract $record) => $record->documentExists() && $record->canBeEditedBy()),

                    EditAction::make()
                        ->color('gray')
                        ->visible(fn (Contract $record) => $record->canBeEditedBy()),

                    Action::make('submitForApproval')
                        ->label(__('app.action.submit_for_approval'))
                        ->icon('heroicon-o-arrow-up-tray')
                        ->color('gray')
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

                    DeleteAction::make()
                        ->visible(fn (Contract $record) => $record->canBeDeletedBy()),
                ])
                    ->icon('heroicon-m-ellipsis-vertical'),
            ])
            ->headerActions([
                Action::make('exportXlsx')
                    ->label(__('app.action.export_xlsx'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->visible(fn (): bool => ViewContract::userCanExportContract())
                    ->action(function ($livewire) {
                        // getFilteredTableQuery() respects every tab, filter and
                        // sort the user has applied — so the file only contains
                        // what they're seeing on screen.
                        return Excel::download(
                            new ContractsExport($livewire->getFilteredTableQuery()),
                            'contracts-'.now()->format('Y-m-d').'.xlsx',
                        );
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
