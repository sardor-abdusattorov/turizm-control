<?php

namespace App\Filament\Resources\PressTours\Tables;

use App\Enums\PressTourDirection;
use App\Enums\PressTourState;
use App\Filament\Support\CreatedAtColumn;
use App\Filament\Support\StatusToggleColumn;
use App\Models\PressTour;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PressToursTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('order'))
            // The registry reads chronologically, and a tour's month is the
            // only orderable part of its free-text period.
            ->defaultSort('starts_month')
            ->columns([
                TextColumn::make('direction')
                    ->label(__('app.label.press_tour_direction'))
                    ->badge()
                    ->formatStateUsing(fn (PressTourDirection $state): string => $state->label())
                    ->color(fn (PressTourDirection $state): string => $state->color())
                    ->sortable(),

                TextColumn::make('name')
                    ->label(__('app.label.press_tour_name'))
                    ->weight('semibold')
                    ->wrap()
                    ->searchable()
                    ->sortable(),

                TextColumn::make('place')
                    ->label(__('app.label.press_tour_place'))
                    ->searchable()
                    ->placeholder(__('app.label.not_set'))
                    ->sortable(),

                TextColumn::make('period')
                    ->label(__('app.label.press_tour_period'))
                    ->placeholder(__('app.label.not_set'))
                    ->sortable('starts_month'),

                // «6+11» and «n/a» both live here, so the column prints what
                // the registry meant rather than a bare number.
                TextColumn::make('people_count')
                    ->label(__('app.label.press_tour_people'))
                    ->state(fn (PressTour $record): string => $record->peopleLabel())
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('responsible')
                    ->label(__('app.label.responsible'))
                    ->state(fn (PressTour $record): string => implode(' · ', $record->responsibleNames()) ?: __('app.label.not_set'))
                    ->wrap()
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('foreign_partner')
                    ->label(__('app.label.press_tour_foreign_partner'))
                    ->placeholder(__('app.label.not_set'))
                    ->wrap()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('state')
                    ->label(__('app.label.press_tour_state'))
                    ->badge()
                    ->formatStateUsing(fn (PressTourState $state): string => $state->label())
                    ->color(fn (PressTourState $state): string => $state->color())
                    ->icon(fn (PressTourState $state): string => $state->icon())
                    ->sortable(),

                // A tour that has happened owes a report pack; an empty count
                // on a held tour is the thing the programme needs to chase.
                TextColumn::make('attachments_count')
                    ->label(__('app.label.press_tour_documents'))
                    ->counts('attachments')
                    ->badge()
                    ->color(fn (?int $state, PressTour $record): string => $record->isHeld() && ! $state ? 'warning' : 'gray')
                    ->formatStateUsing(fn (?int $state, PressTour $record): string => $state
                        ? (string) $state
                        : ($record->isHeld() ? __('app.label.press_tour_documents_missing') : '—'))
                    ->sortable(),

                TextColumn::make('held_on')
                    ->label(__('app.label.press_tour_held_on'))
                    ->date('d.m.Y')
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('order.number')
                    ->label(__('app.label.order_basis'))
                    ->badge()
                    ->color('gray')
                    ->placeholder(__('app.label.not_set'))
                    ->toggleable(),

                StatusToggleColumn::make()->sortable(),

                CreatedAtColumn::make()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('direction')
                    ->label(__('app.label.press_tour_direction'))
                    ->options(PressTourDirection::options()),

                SelectFilter::make('state')
                    ->label(__('app.label.press_tour_state'))
                    ->options(PressTourState::options()),

                // The working question after a trip: what has happened and
                // still has nothing filed against it.
                Filter::make('awaiting_documents')
                    ->label(__('app.label.press_tour_documents_missing'))
                    ->query(fn (Builder $query): Builder => $query
                        ->where('state', PressTourState::Held->value)
                        ->whereDoesntHave('attachments')),

                SelectFilter::make('starts_month')
                    ->label(__('app.label.press_tour_month'))
                    ->options(PressTour::monthOptions()),

                SelectFilter::make('order_id')
                    ->label(__('app.label.order_basis'))
                    ->relationship('order', 'number')
                    ->searchable()
                    ->preload(),
            ])
            ->filtersFormColumns(2)
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make()->color('gray'),
                    EditAction::make()->color('gray'),
                    DeleteAction::make(),
                ])->icon('heroicon-m-ellipsis-vertical'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
