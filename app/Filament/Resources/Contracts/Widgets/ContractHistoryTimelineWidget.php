<?php

namespace App\Filament\Resources\Contracts\Widgets;

use App\Models\Contract;
use App\Support\ContractActivity;
use Devletes\FilamentTimelineView\Tables\Columns\TimelineEntry;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;

/**
 * The contract's execution history rendered as a chronological timeline
 * (devletes/filament-timeline-view) — date-grouped cards fed by the same
 * activity log the old hand-rolled feed read, with a workflow/edits filter.
 */
class ContractHistoryTimelineWidget extends TableWidget
{
    public int $contractId;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('app.label.execution_history'))
            ->query(fn (): Builder => Activity::query()
                ->where('subject_type', (new Contract)->getMorphClass())
                ->where('subject_id', $this->contractId)
                ->with('causer')
                ->latest())
            ->columns([
                TimelineEntry::make()
                    ->title(fn (Activity $record): string => ContractActivity::label($record->event ?? '', $record->description))
                    ->content(fn (Activity $record): ?string => data_get($record->properties, 'comment'))
                    ->author(fn (Activity $record): string => $record->causer?->name ?? __('app.label.system'))
                    ->time('created_at', 'H:i'),
            ])
            ->defaultGroup(
                Group::make('created_at')
                    ->label(__('app.label.date'))
                    ->date()
            )
            ->filters([
                SelectFilter::make('group')
                    ->label(__('app.label.event_kind'))
                    ->options([
                        'workflow' => __('app.label.workflow_events'),
                        'edit' => __('app.label.edit_events'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'workflow' => $query->whereIn('event', ContractActivity::workflowEvents()),
                        'edit' => $query->whereNotIn('event', ContractActivity::workflowEvents()),
                        default => $query,
                    }),
            ])
            ->paginated([15, 30])
            ->defaultPaginationPageOption(15)
            ->emptyStateHeading(__('app.label.no_history'))
            ->emptyStateIcon('heroicon-o-clock')
            ->asTimeline();
    }
}
