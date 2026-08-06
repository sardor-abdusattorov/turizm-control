<?php

namespace App\Filament\Resources\Projects\Tables;

use App\Enums\ProjectType;
use App\Exports\ProjectsRegistryExport;
use App\Filament\Resources\Projects\BaseProjectResource;
use App\Filament\Support\CreatedAtColumn;
use App\Filament\Support\ExportPermission;
use App\Filament\Support\ExportXlsxAction;
use App\Filament\Support\StatusToggleColumn;
use App\Filament\Widgets\Dashboard\ProjectContractsTableWidget;
use App\Filament\Widgets\Dashboard\ProjectParticipantsTableWidget;
use App\Models\Contract;
use App\Models\Project;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Facades\Excel;

class ProjectsTable
{
    /**
     * Currency suffix for the fee columns — a project's income contracts are
     * signed in a single currency in practice (the registry), so the first
     * code labels the total; a mixed project gets no (misleading) suffix.
     */
    protected static function feeCurrencySuffix(Project $record): string
    {
        $codes = $record->incomeContracts->pluck('currency.short_name')->filter()->unique();

        return $codes->count() === 1 ? ' '.$codes->first() : '';
    }

    /**
     * The table is shared by both typed list pages; local-event columns
     * (смета/итог/чел) only make sense on the internal listing.
     */
    protected static function isInternalList($livewire): bool
    {
        return $livewire::getResource()::projectType() === ProjectType::Internal;
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with(['creator', 'incomeContracts.currency'])
                ->withCount([
                    'feeContracts as participants_count' => fn (Builder $q) => $q->visibleTo()->where('status', '!=', Contract::STATUS_REJECTED->value),
                    'sponsorshipContracts as sponsors_count' => fn (Builder $q) => $q->visibleTo()->where('status', '!=', Contract::STATUS_REJECTED->value),
                    'contracts' => fn (Builder $q) => $q->visibleTo(),
                ])
                ->withSum(['incomeContracts as participants_sum_amount' => fn (Builder $q) => $q->visibleTo()->where('status', '!=', Contract::STATUS_REJECTED->value)], 'amount'))
            ->defaultSort('starts_on', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->label(__('app.label.project_name'))
                    ->weight('semibold')
                    ->searchable()
                    ->sortable()
                    ->extraCellAttributes(['style' => 'min-width: 22rem'])
                    ->wrap()
                    ->lineClamp(10)
                    ->tooltip(fn (Project $record): ?string => $record->name),

                TextColumn::make('venue')
                    ->label(__('app.label.venue'))
                    ->searchable()
                    ->sortable()
                    ->placeholder(__('app.label.not_set'))
                    ->toggleable(),

                TextColumn::make('starts_on')
                    ->label(__('app.label.starts_on'))
                    ->date('d.m.Y')
                    ->alignCenter()
                    ->sortable(),

                TextColumn::make('ends_on')
                    ->label(__('app.label.ends_on'))
                    ->date('d.m.Y')
                    ->alignCenter()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('area_sqm')
                    ->label(__('app.label.area_sqm'))
                    ->formatStateUsing(fn (?string $state): string => Money::format($state).' м²')
                    ->placeholder(__('app.label.not_set'))
                    ->alignEnd()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('participants_count')
                    ->label(__('app.label.participants'))
                    ->badge()
                    ->color(fn (Project $record): string => ($record->participants_count ?? 0) > 0 ? 'info' : 'gray')
                    ->alignCenter()
                    ->tooltip(fn (Project $record): ?string => ($record->participants_count ?? 0) > 0
                        ? __('app.label.participants_breakdown_hint')
                        : null)
                    ->action(self::participantBreakdownAction(
                        'participantsBreakdown',
                        false,
                        'heroicon-o-user-group',
                    ))
                    ->sortable(),

                TextColumn::make('sponsors_count')
                    ->label(__('app.label.sponsors'))
                    ->badge()
                    ->color(fn (Project $record): string => ($record->sponsors_count ?? 0) > 0 ? 'warning' : 'gray')
                    ->alignCenter()
                    ->tooltip(fn (Project $record): ?string => ($record->sponsors_count ?? 0) > 0
                        ? __('app.label.sponsors_breakdown_hint')
                        : null)
                    ->action(self::participantBreakdownAction(
                        'sponsorsBreakdown',
                        true,
                        'heroicon-o-star',
                    ))
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('contracts_count')
                    ->label(__('app.label.contracts'))
                    ->badge()
                    ->alignCenter()
                    ->state(fn (Project $record): int => (int) ($record->contracts_count ?? 0))
                    ->color(fn (Project $record): string => ($record->contracts_count ?? 0) > 0 ? 'primary' : 'gray')
                    ->tooltip(fn (Project $record): ?string => ($record->contracts_count ?? 0) > 0
                        ? __('app.label.contracts_breakdown_hint')
                        : null)
                    ->action(
                        Action::make('projectContractsBreakdown')
                            ->modalHeading(fn (Project $record): string => $record->name)
                            ->modalIcon('heroicon-o-document-text')
                            ->modalWidth('6xl')
                            ->modalContent(fn (Project $record) => view('filament.partials.embedded-table', [
                                'widget' => ProjectContractsTableWidget::class,
                                'params' => ['pageFilters' => ['projectId' => $record->id], 'hideHeading' => true],
                                'key' => 'project-contracts-modal-'.$record->id,
                            ]))
                            ->modalSubmitAction(false)
                            ->modalCancelAction(false),
                    )
                    ->sortable()
                    ->toggleable(),

                ImageColumn::make('gallery')
                    ->label(__('app.label.gallery'))
                    ->state(fn (Project $record): array => array_values(array_filter(
                        $record->gallery ?? [],
                        fn (string $path): bool => ! Project::isVideoPath($path),
                    )))
                    ->disk('local')
                    ->imageHeight(36)
                    ->stacked()
                    ->limit(3)
                    ->limitedRemainingText()
                    ->toggleable(),

                TextColumn::make('participants_sum_amount')
                    ->label(__('app.label.fees_total'))
                    ->formatStateUsing(fn (?string $state, Project $record): string => Money::format($state).self::feeCurrencySuffix($record))
                    ->placeholder('0')
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('participants_sum_paid_amount')
                    ->label(__('app.label.paid'))
                    ->state(fn (Project $record): float => $record->incomeContracts
                        ->reject(fn (Contract $c): bool => $c->status === Contract::STATUS_REJECTED)
                        ->sum(fn (Contract $c): float => $c->paidAmount()))
                    ->formatStateUsing(fn (?string $state, Project $record): string => Money::format($state).self::feeCurrencySuffix($record))
                    ->placeholder('0')
                    ->color('success')
                    ->alignEnd()
                    ->toggleable(),

                TextColumn::make('creator.name')
                    ->label(__('app.label.created_by'))
                    ->placeholder(__('app.label.not_set'))
                    ->sortable()
                    ->toggleable(),

                StatusToggleColumn::make()
                    ->sortable(),

                CreatedAtColumn::make()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('year')
                    ->label(__('app.label.year'))
                    ->options(fn (): array => Project::query()
                        ->whereNotNull('starts_on')
                        ->pluck('starts_on')
                        ->map(fn ($date) => $date->year)
                        ->unique()
                        ->sortDesc()
                        ->mapWithKeys(fn (int $year): array => [$year => (string) $year])
                        ->all())
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['value'] ?? null, fn (Builder $q, $year) => $q->whereYear('starts_on', $year))),

                SelectFilter::make('status')
                    ->label(__('app.label.status'))
                    ->options(Project::getStatuses()),
            ])
            ->filtersFormColumns(2)
            ->recordUrl(fn (Project $record) => BaseProjectResource::resourceFor($record)::getUrl('view', ['record' => $record]))
            ->headerActions([
                ExportXlsxAction::make()
                    ->visible(fn (): bool => ExportPermission::allows('export_project'))
                    ->action(fn ($livewire) => Excel::download(
                        new ProjectsRegistryExport(
                            $livewire->getFilteredTableQuery(),
                            $livewire::getResource()::projectType(),
                        ),
                        $livewire::getResource()::projectType()->value.'-projects-'.now()->format('Y-m-d').'.xlsx',
                    )),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make()->color('gray'),

                    EditAction::make()->color('gray'),

                    DeleteAction::make(),
                ])
                    ->icon('heroicon-m-ellipsis-vertical'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn (): bool => auth()->user()?->can('delete_project') ?? false),
                ]),
            ]);
    }

    /**
     * Role-scoped breakdown behind the participants / sponsors count badge —
     * a stock Filament table (ProjectParticipantsTableWidget) scoped to one
     * income kind, the same table the project view page's tab embeds.
     */
    private static function participantBreakdownAction(string $name, bool $sponsors, string $icon): Action
    {
        return Action::make($name)
            ->modalHeading(fn (Project $record): string => $record->name)
            ->modalIcon($icon)
            ->modalWidth('6xl')
            ->modalContent(fn (Project $record) => view('filament.partials.embedded-table', [
                'widget' => ProjectParticipantsTableWidget::class,
                'params' => [
                    'pageFilters' => ['projectId' => $record->id],
                    'kind' => $sponsors ? 'sponsors' : 'participants',
                ],
                'key' => $name.'-'.$record->id,
            ]))
            ->modalSubmitAction(false)
            ->modalCancelAction(false);
    }
}
