<?php

namespace App\Filament\Widgets;

use App\Support\ContractActivity;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Devletes\FilamentTimelineView\Tables\Columns\TimelineEntry;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Activity;

class DocumentHistoryTimelineWidget extends TableWidget
{
    use HasWidgetShield;

    public string $subjectType;

    public int $subjectId;

    protected int|string|array $columnSpan = 'full';

    /** @return array<string, mixed> */
    public static function paramsFor(Model $record): array
    {
        return [
            'subjectType' => $record->getMorphClass(),
            'subjectId' => $record->getKey(),
        ];
    }

    public function table(Table $table): Table
    {
        return $table

            ->heading(null)
            ->query(fn (): Builder => Activity::query()
                ->where('subject_type', $this->subjectType)
                ->where('subject_id', $this->subjectId)
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
