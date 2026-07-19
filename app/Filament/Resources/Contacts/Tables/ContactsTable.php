<?php

namespace App\Filament\Resources\Contacts\Tables;

use App\Enums\ContractDirection;
use App\Exports\ContactsExport;
use App\Filament\Resources\Contacts\ContactResource;
use App\Filament\Support\CreatedAtColumn;
use App\Filament\Support\ExportPermission;
use App\Filament\Support\StatusToggleColumn;
use App\Models\Contact;
use App\Models\Contract;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Facades\Excel;

class ContactsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->withRoleFlags()
                ->withCount([
                    'contracts' => fn (Builder $contracts) => $contracts->visibleTo(),
                    'incomeContracts' => fn (Builder $contracts) => $contracts
                        ->visibleTo()
                        ->where('status', '!=', Contract::STATUS_REJECTED->value),
                ]))
            ->columns([
                TextColumn::make('type')
                    ->label(__('app.label.contact_type'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => Contact::getTypes()[$state] ?? $state)
                    ->color(fn (string $state) => Contact::getTypeColors()[$state] ?? 'gray')
                    ->sortable(),

                TextColumn::make('name')
                    ->label(__('app.label.contact_name'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('role')
                    ->label(__('app.label.contact_role'))
                    ->badge()
                    ->state(fn (Contact $record): array => array_values(array_filter([
                        ($record->is_supplier ?? false) ? 'supplier' : null,
                        ($record->is_client ?? false) ? 'client' : null,
                    ])))
                    ->formatStateUsing(fn (string $state): string => __('app.label.role_'.$state))
                    ->color(fn (string $state): string => $state === 'supplier' ? 'warning' : 'success')
                    ->placeholder('—'),

                TextColumn::make('contracts_count')
                    ->label(__('app.label.contracts'))
                    ->badge()
                    ->alignCenter()
                    ->state(fn (Contact $record): int => (int) ($record->contracts_count ?? 0))
                    ->color(fn (Contact $record): string => ($record->contracts_count ?? 0) > 0 ? 'info' : 'gray')
                    ->tooltip(fn (Contact $record): ?string => ($record->contracts_count ?? 0) > 0
                        ? __('app.label.contracts_breakdown_hint')
                        : null)
                    ->action(
                        Action::make('contractsBreakdown')
                            ->modalHeading(fn (Contact $record): string => $record->name)
                            ->modalIcon('heroicon-o-document-text')
                            ->modalContent(fn (Contact $record) => view(
                                'filament.resources.contacts.contracts-breakdown',
                                [
                                    'contracts' => $record->visibleContracts(),
                                    'totals' => $record->contractTotalsByCurrency(),
                                ],
                            ))
                            ->modalSubmitAction(false)
                            ->modalCancelAction(false),
                    )
                    ->sortable(),

                TextColumn::make('income_contracts_count')
                    ->label(__('app.label.projects'))
                    ->badge()
                    ->alignCenter()
                    ->state(fn (Contact $record): int => (int) ($record->income_contracts_count ?? 0))
                    ->color(fn (Contact $record): string => ($record->income_contracts_count ?? 0) > 0 ? 'warning' : 'gray')
                    ->tooltip(fn (Contact $record): ?string => ($record->income_contracts_count ?? 0) > 0
                        ? __('app.label.projects_breakdown_hint')
                        : null)
                    ->action(
                        Action::make('projectsBreakdown')
                            ->modalHeading(fn (Contact $record): string => $record->name)
                            ->modalIcon('heroicon-o-presentation-chart-bar')
                            ->modalContent(fn (Contact $record) => view(
                                'filament.resources.contacts.projects-breakdown',
                                [
                                    'participations' => $record->incomeContracts()
                                        ->visibleTo()
                                        ->where('status', '!=', Contract::STATUS_REJECTED->value)
                                        ->with(['project', 'currency', 'contractType'])
                                        ->get(),
                                    'totals' => $record->projectTotalsByCurrency(),
                                ],
                            ))
                            ->modalSubmitAction(false)
                            ->modalCancelAction(false),
                    )
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('inn')
                    ->label(__('app.label.inn'))
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('pinfl')
                    ->label(__('app.label.pinfl'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('contact_person')
                    ->label(__('app.label.contact_person'))
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('phone')
                    ->label(__('app.label.phone'))
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('email')
                    ->label(__('app.label.email'))
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('website')
                    ->label(__('app.label.website'))
                    ->url(fn (Contact $record): ?string => $record->website, shouldOpenInNewTab: true)
                    ->toggleable(isToggledHiddenByDefault: true),

                StatusToggleColumn::make()
                    ->sortable(),

                CreatedAtColumn::make()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label(__('app.label.contact_type'))
                    ->options(Contact::getTypes()),

                SelectFilter::make('role')
                    ->label(__('app.label.contact_role'))
                    ->options([
                        'supplier' => __('app.label.role_supplier'),
                        'client' => __('app.label.role_client'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        if (in_array($value, [null, ''], true)) {
                            return $query;
                        }

                        $direction = $value === 'supplier'
                            ? ContractDirection::Expense
                            : ContractDirection::Income;

                        return $query->whereHas(
                            'contracts',
                            fn (Builder $contracts) => $contracts->whereHas(
                                'contractType',
                                fn (Builder $type) => $type->where('direction', $direction->value),
                            ),
                        );
                    }),

                SelectFilter::make('status')
                    ->label(__('app.label.status'))
                    ->options(Contact::getStatuses()),
            ])
            ->filtersFormColumns(2)
            ->recordUrl(fn (Contact $record) => ContactResource::getUrl('view', ['record' => $record]))
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    DeleteAction::make(),
                ])
                    ->icon('heroicon-m-ellipsis-vertical'),
            ])
            ->headerActions([
                Action::make('exportXlsx')
                    ->label(__('app.action.export_xlsx'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->visible(fn (): bool => ExportPermission::allows('export_contact'))
                    ->action(fn ($livewire) => Excel::download(
                        new ContactsExport($livewire->getFilteredTableQuery()),
                        'contacts-'.now()->format('Y-m-d').'.xlsx',
                    )),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn (): bool => auth()->user()?->can('delete_contact') ?? false),
                ]),
            ]);
    }
}
