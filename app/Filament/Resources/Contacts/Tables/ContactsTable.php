<?php

namespace App\Filament\Resources\Contacts\Tables;

use App\Exports\ContactsExport;
use App\Filament\Resources\Contacts\ContactResource;
use App\Filament\Resources\Contracts\ContractResource;
use App\Filament\Support\CreatedAtColumn;
use App\Filament\Support\ExportPermission;
use App\Filament\Support\StatusToggleColumn;
use App\Models\Contact;
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
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount([
                'contracts' => fn (Builder $contracts) => $contracts->visibleTo(),
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

                TextColumn::make('contracts_count')
                    ->label(__('app.label.contracts'))
                    ->badge()
                    ->alignCenter()
                    ->state(fn (Contact $record): int => (int) ($record->contracts_count ?? 0))
                    ->color(fn (Contact $record): string => ($record->contracts_count ?? 0) > 0 ? 'info' : 'gray')
                    ->action(
                        Action::make('contractsBreakdown')
                            ->modalHeading(fn (Contact $record): string => $record->name)
                            ->modalIcon('heroicon-o-document-text')
                            ->modalContent(fn (Contact $record) => view(
                                'filament.resources.contacts.contracts-breakdown',
                                ['rows' => $record->contractTotalsByCurrency()],
                            ))
                            ->modalSubmitAction(false)
                            ->modalCancelActionLabel(__('app.action.close'))
                            ->extraModalFooterActions([
                                Action::make('viewContracts')
                                    ->label(__('app.action.details'))
                                    ->icon('heroicon-o-arrow-top-right-on-square')
                                    ->url(fn (Contact $record): string => ContractResource::getUrl('index', [
                                        'tableFilters' => ['contact_id' => ['value' => $record->id]],
                                    ]))
                                    ->visible(fn (Contact $record): bool => (int) ($record->contracts_count ?? 0) > 0),
                            ]),
                    ),

                TextColumn::make('inn')
                    ->label(__('app.label.inn'))
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('pinfl')
                    ->label(__('app.label.pinfl'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('contact_person')
                    ->label(__('app.label.contact_person'))
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('phone')
                    ->label(__('app.label.phone'))
                    ->toggleable(),

                TextColumn::make('email')
                    ->label(__('app.label.email'))
                    ->toggleable(),

                StatusToggleColumn::make(),

                CreatedAtColumn::make()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label(__('app.label.contact_type'))
                    ->options(Contact::getTypes()),

                SelectFilter::make('status')
                    ->label(__('app.label.status'))
                    ->options(Contact::getStatuses()),
            ])
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
