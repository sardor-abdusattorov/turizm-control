<?php

namespace App\Filament\Resources\Sponsors\Tables;

use App\Exports\SponsorsExport;
use App\Filament\Support\CreatedAtColumn;
use App\Filament\Support\ExportPermission;
use App\Filament\Support\StatusToggleColumn;
use App\Models\Sponsor;
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

class SponsorsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount('participations'))
            ->columns([
                TextColumn::make('name')
                    ->label(__('app.label.name'))
                    ->weight('semibold')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('participations_count')
                    ->label(__('app.label.projects'))
                    ->badge()
                    ->alignCenter()
                    ->color(fn (Sponsor $record): string => ($record->participations_count ?? 0) > 0 ? 'warning' : 'gray')
                    ->sortable(),

                TextColumn::make('inn')
                    ->label(__('app.label.inn'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('contact_person')
                    ->label(__('app.label.contact_person'))
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('phone')
                    ->label(__('app.label.phone'))
                    ->toggleable(),

                TextColumn::make('email')
                    ->label(__('app.label.email'))
                    ->toggleable(),

                TextColumn::make('website')
                    ->label(__('app.label.website'))
                    ->url(fn (Sponsor $record): ?string => $record->website, shouldOpenInNewTab: true)
                    ->toggleable(),

                StatusToggleColumn::make()
                    ->sortable(),

                CreatedAtColumn::make()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('app.label.status'))
                    ->options(Sponsor::getStatuses()),
            ])
            ->headerActions([
                Action::make('exportXlsx')
                    ->label(__('app.action.export_xlsx'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->visible(fn (): bool => ExportPermission::allows('export_sponsor'))
                    ->action(fn ($livewire) => Excel::download(
                        new SponsorsExport($livewire->getFilteredTableQuery()),
                        'sponsors-'.now()->format('Y-m-d').'.xlsx',
                    )),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    DeleteAction::make(),
                ])
                    ->icon('heroicon-m-ellipsis-vertical'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn (): bool => auth()->user()?->can('delete_sponsor') ?? false),
                ]),
            ]);
    }
}
