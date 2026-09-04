<?php

namespace App\Filament\Resources\Sponsors\Tables;

use App\Exports\SponsorsExport;
use App\Filament\Support\CreatedAtColumn;
use App\Filament\Support\ExportPermission;
use App\Filament\Support\ExportXlsxAction;
use App\Filament\Support\StatusToggleColumn;
use App\Filament\Support\UpdatedAtColumn;
use App\Filament\Widgets\Counterparty\CounterpartyProjectsTableWidget;
use App\Models\Contract;
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
            ->defaultSort('updated_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount([
                'sponsorshipContracts' => fn (Builder $contracts): Builder => $contracts
                    ->visibleTo()
                    ->where('status', '!=', Contract::STATUS_REJECTED->value),
            ]))
            ->columns([
                TextColumn::make('name')
                    ->label(__('app.label.name'))
                    ->weight('semibold')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('sponsorship_contracts_count')
                    ->label(__('app.label.projects'))
                    ->badge()
                    ->alignCenter()
                    ->color(fn (Sponsor $record): string => ($record->sponsorship_contracts_count ?? 0) > 0 ? 'warning' : 'gray')
                    ->tooltip(fn (Sponsor $record): ?string => ($record->sponsorship_contracts_count ?? 0) > 0
                        ? __('app.label.projects_breakdown_hint')
                        : null)
                    ->action(
                        Action::make('projectsBreakdown')
                            ->modalHeading(fn (Sponsor $record): string => $record->name)
                            ->modalIcon('heroicon-o-presentation-chart-bar')
                            ->modalWidth('6xl')
                            ->modalContent(fn (Sponsor $record) => view('filament.partials.embedded-table', [
                                'widget' => CounterpartyProjectsTableWidget::class,
                                'params' => ['sponsorId' => $record->id],
                                'key' => 'sponsor-projects-modal-'.$record->id,
                            ]))
                            ->modalSubmitAction(false)
                            ->modalCancelAction(false),
                    )
                    ->sortable(),

                TextColumn::make('inn')
                    ->label(__('app.label.inn'))
                    ->searchable()
                    ->sortable()
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

                UpdatedAtColumn::make(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('app.label.status'))
                    ->options(Sponsor::getStatuses()),
            ])
            ->headerActions([
                ExportXlsxAction::make()
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
