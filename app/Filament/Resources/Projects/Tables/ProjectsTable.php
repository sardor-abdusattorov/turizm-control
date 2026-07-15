<?php

namespace App\Filament\Resources\Projects\Tables;

use App\Enums\ParticipantRole;
use App\Enums\ProjectType;
use App\Exports\ProjectsRegistryExport;
use App\Filament\Resources\Projects\BaseProjectResource;
use App\Filament\Support\CreatedAtColumn;
use App\Filament\Support\ExportPermission;
use App\Filament\Support\StatusToggleColumn;
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
     * Currency suffix for the fee columns — participants of one project pay
     * in a single currency in practice (the registry), so the first row's
     * code labels the total; a mixed project gets no (misleading) suffix.
     */
    protected static function feeCurrencySuffix(Project $record): string
    {
        $codes = $record->participants->pluck('currency.short_name')->filter()->unique();

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
                // participants.currency backs the currency suffix on the two
                // money columns — one eager load instead of a per-row query.
                ->with(['creator', 'participants.currency'])
                ->withCount([
                    'participants',
                    'participants as sponsors_count' => fn (Builder $participants) => $participants
                        ->where('role', ParticipantRole::Sponsor),
                    // Same visibility rule as everywhere: the badge only counts
                    // the contracts the viewer is allowed to see.
                    'contracts' => fn (Builder $contracts) => $contracts->visibleTo(),
                ])
                ->withSum('participants', 'amount')
                ->withSum('participants', 'paid_amount'))
            ->defaultSort('starts_on', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->label(__('app.label.project_name'))
                    ->weight('semibold')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('venue')
                    ->label(__('app.label.venue'))
                    ->limit(24)
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('starts_on')
                    ->label(__('app.label.starts_on'))
                    ->date('d.m.Y')
                    ->sortable(),

                TextColumn::make('ends_on')
                    ->label(__('app.label.ends_on'))
                    ->date('d.m.Y')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('area_sqm')
                    ->label(__('app.label.area_sqm'))
                    ->formatStateUsing(fn (?string $state): string => Money::format($state).' м²')
                    ->placeholder('—')
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
                        ParticipantRole::Participant,
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
                        ParticipantRole::Sponsor,
                        'heroicon-o-star',
                    ))
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
                            ->modalContent(fn (Project $record) => view(
                                'filament.resources.projects.tables.contracts-breakdown',
                                [
                                    'contracts' => $record->visibleContracts(),
                                    'totals' => $record->visibleContractTotalsByCurrency(),
                                ],
                            ))
                            ->modalSubmitAction(false)
                            ->modalCancelAction(false),
                    )
                    ->toggleable(),

                ImageColumn::make('gallery')
                    ->label(__('app.label.gallery'))
                    ->disk('local')
                    ->imageGallery()
                    ->imageHeight(36)
                    ->stacked()
                    ->limit(3)
                    ->remainingTextBadge()
                    ->toggleable(),

                TextColumn::make('participants_sum_amount')
                    ->label(__('app.label.fees_total'))
                    ->formatStateUsing(fn (?string $state, Project $record): string => Money::format($state).self::feeCurrencySuffix($record))
                    ->placeholder('0')
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('participants_sum_paid_amount')
                    ->label(__('app.label.paid'))
                    ->formatStateUsing(fn (?string $state, Project $record): string => Money::format($state).self::feeCurrencySuffix($record))
                    ->placeholder('0')
                    ->color('success')
                    ->alignEnd()
                    ->toggleable(),

                TextColumn::make('estimate_amount')
                    ->label(__('app.label.estimate_amount'))
                    ->formatStateUsing(fn (?string $state): string => Money::format($state).' UZS')
                    ->placeholder('—')
                    ->alignEnd()
                    ->visible(self::isInternalList(...))
                    ->toggleable(),

                TextColumn::make('final_amount')
                    ->label(__('app.label.final_amount'))
                    ->formatStateUsing(fn (?string $state): string => Money::format($state).' UZS')
                    ->placeholder('—')
                    ->alignEnd()
                    ->visible(self::isInternalList(...))
                    ->toggleable(),

                TextColumn::make('attendees_count')
                    ->label(__('app.label.attendees_count'))
                    ->placeholder('—')
                    ->alignCenter()
                    ->visible(self::isInternalList(...))
                    ->toggleable(),

                TextColumn::make('creator.name')
                    ->label(__('app.label.created_by'))
                    ->placeholder('—')
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
                Action::make('exportXlsx')
                    ->label(__('app.action.export_xlsx'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
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
     * the record-list modal the sponsor and contact lists set as the
     * pattern: one row per participation, per-currency totals at the foot.
     */
    private static function participantBreakdownAction(string $name, ParticipantRole $role, string $icon): Action
    {
        return Action::make($name)
            ->modalHeading(fn (Project $record): string => $record->name)
            ->modalIcon($icon)
            ->modalContent(fn (Project $record) => view(
                'filament.resources.projects.tables.participants-breakdown',
                [
                    'rows' => $record->participants->where('role', $role)->values(),
                    'totals' => $record->participantTotalsByCurrency($role),
                    'empty' => $role === ParticipantRole::Sponsor
                        ? __('app.message.no_sponsors')
                        : __('app.message.no_participants'),
                ],
            ))
            ->modalSubmitAction(false)
            ->modalCancelAction(false);
    }
}
