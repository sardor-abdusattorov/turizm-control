<?php

namespace App\Filament\Resources\Contracts\Tables;

use App\Enums\PaymentStatus;
use App\Exports\ContractsExport;
use App\Filament\Resources\Contracts\ContractResource;
use App\Filament\Resources\Contracts\Pages\ViewContract;
use App\Models\Contact;
use App\Models\Contract;
use App\Models\ContractType;
use App\Models\Currency;
use App\Models\User;
use App\Services\Contracts\ContractWorkflow;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Maatwebsite\Excel\Facades\Excel;

class ContractsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Every visible column reads from a relation (status, chain,
            // contact, currency, responsible, payments). Without eager
            // loading the listing fires hundreds of queries per page; this
            // keeps it to a flat handful.
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'contact',
                'contractType',
                'project',
                'currency',
                'responsible',
                'activeApprovers.user.department',
            ]))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('number')
                    ->label(__('app.label.contract_number'))
                    ->weight('semibold')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('contractType.title')
                    ->label(__('app.label.contract_type_single'))
                    ->badge()
                    ->color(fn (Contract $record): string => $record->contractType?->direction?->color() ?? 'gray')
                    ->placeholder('—')
                    ->toggleable(),

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

                TextColumn::make('project.name')
                    ->label(__('app.label.project_single'))
                    ->limit(30)
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('amount')
                    ->label(__('app.label.amount'))
                    ->formatStateUsing(fn (?string $state, Contract $record): string => number_format((float) $state, 2, ',', ' ').' '.($record->currency?->short_name ?? ''))
                    ->sortable(),

                ViewColumn::make('approvers_chain')
                    ->label(__('app.label.approvers'))
                    ->view('filament.resources.contracts.tables.approvers-column')
                    ->disabledClick()
                    ->visible(fn (): bool => ContractWorkflow::approvalEnabled())
                    ->extraHeaderAttributes(['class' => 'contracts-col-approvers'])
                    ->extraAttributes(['class' => 'contracts-col-approvers']),

                ViewColumn::make('sla')
                    ->label(__('app.label.due'))
                    ->view('filament.components.sla-countdown')
                    ->state(fn (Contract $record) => $record->currentApprover()?->due_at)
                    ->visible(fn (): bool => ContractWorkflow::approvalEnabled())
                    ->disabledClick(),

                TextColumn::make('responsible.name')
                    ->label(__('app.label.responsible'))
                    ->toggleable(),

                ViewColumn::make('payment_status')
                    ->label(__('app.label.payment_status'))
                    ->view('filament.resources.contracts.tables.payment-column')
                    ->sortable(),

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
                    ->searchable()
                    ->visible(fn (): bool => self::canFilterByResponsible()),

                SelectFilter::make('currency_id')
                    ->label(__('app.label.currency_single'))
                    ->options(fn () => Currency::query()->where('status', true)->orderBy('short_name')->pluck('short_name', 'id')),

                SelectFilter::make('contact_id')
                    ->label(__('app.label.contact_single'))
                    ->options(fn () => Contact::getActive())
                    ->searchable(),

                SelectFilter::make('project_id')
                    ->label(__('app.label.project_single'))
                    ->relationship('project', 'name')
                    ->searchable(),

                SelectFilter::make('contract_type_id')
                    ->label(__('app.label.contract_type_single'))
                    ->options(fn () => ContractType::getActive())
                    ->searchable(),

                SelectFilter::make('payment_status')
                    ->label(__('app.label.payment_status'))
                    ->options(PaymentStatus::options()),

                Filter::make('created_at')
                    ->label(__('app.label.created_at'))
                    ->schema([
                        DatePicker::make('from')->label(__('app.label.from')),
                        DatePicker::make('until')->label(__('app.label.until')),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date))),
            ], layout: FiltersLayout::AboveContentCollapsible)
            // The heaviest filter set in the app: a grid above the table
            // (collapsed by default) instead of one endless dropdown column.
            ->filtersFormColumns([
                'md' => 2,
                'xl' => 4,
            ])
            ->recordUrl(fn (Contract $record) => ContractResource::getUrl('view', ['record' => $record]))
            ->recordActions([
                Action::make('contractFlow')
                    ->hiddenLabel()
                    ->visible(fn (): bool => ContractWorkflow::approvalEnabled())
                    ->modalSubmitAction(false)
                    ->modalCancelAction(false)
                    ->modalWidth('4xl')
                    ->modalHeading(fn (Contract $record): string => trim(($record->number ? $record->number.' · ' : '').($record->title ?? '')) ?: __('app.label.approval_chain'))
                    ->modalContent(fn (Contract $record) => view(
                        'filament.resources.contracts.tables.approval-flow-modal',
                        ['contract' => $record],
                    ))
                    ->extraAttributes(['class' => 'is-action-hidden']),

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
                        ->visible(fn (Contract $record) => $record->documentExists()
                            && $record->status === Contract::STATUS_APPROVED),

                    Action::make('downloadPdf')
                        ->label(__('app.action.download_pdf'))
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('gray')
                        ->url(fn (Contract $record) => route('contracts.pdf.download', ['contract' => $record]))
                        ->visible(fn (Contract $record): bool => $record->status === Contract::STATUS_APPROVED
                            && ViewContract::userCanExportContract()),

                    EditAction::make()
                        ->color('gray')
                        ->visible(fn (Contract $record) => $record->canBeEditedBy()),

                    Action::make('submitForApproval')
                        ->label(__('app.action.submit_for_approval'))
                        ->icon('heroicon-o-paper-airplane')
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
                        return Excel::download(
                            new ContractsExport($livewire->getFilteredTableQuery()),
                            'contracts-'.now()->format('Y-m-d').'.xlsx',
                        );
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        // Shield generates no `deleteAny` ability, so Filament
                        // would otherwise show bulk delete to anyone who can
                        // open the list. Gate it on the delete permission.
                        ->visible(fn (): bool => auth()->user()?->can('delete_contract') ?? false)
                        // The per-row Delete button is gated by canBeDeletedBy;
                        // make sure the bulk variant respects the same rule —
                        // only Drafts owned by the user (or super_admin) get
                        // dropped, the rest of the selection is left alone.
                        ->action(function (Collection $records) {
                            $deletable = $records->filter(
                                fn (Contract $contract): bool => $contract->canBeDeletedBy(),
                            );

                            $deletable->each->delete();

                            $skipped = $records->count() - $deletable->count();

                            Notification::make()
                                ->title(__('app.message.bulk_delete_done', [
                                    'deleted' => $deletable->count(),
                                    'skipped' => $skipped,
                                ]))
                                ->success()
                                ->send();
                        }),
                ]),
            ]);
    }

    /**
     * The "Responsible" filter only makes sense for users who can see more
     * than their own contracts. A manager's list is already scoped to
     * themselves, so the picker would be a confusing no-op — show it to
     * oversight (and view_all_contracts holders) only.
     */
    private static function canFilterByResponsible(): bool
    {
        $user = auth()->user();

        return $user !== null
            && ($user->hasAnyRole(Contract::OVERSIGHT_ROLES) || $user->can('view_all_contracts'));
    }
}
