<?php

namespace App\Filament\Widgets;

use App\Enums\ApprovalStatus;
use App\Models\Approval;
use App\Models\Requisition;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Actions\Action;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ApprovalsTimelineWidget extends TableWidget
{
    use HasWidgetShield;

    public int $requisitionId;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading(null)
            ->columnManager(false)
            ->query(fn (): Builder => Approval::query()
                ->whereKey($this->visibleApprovalIds())
                ->with(['user.department', 'user.position'])
                ->orderByRaw('CASE WHEN status = ? THEN 1 ELSE 0 END', [ApprovalStatus::Invalidated->value])
                ->orderBy('order')
                ->orderBy('id'))
            ->description(fn (): string => $this->progressSummary())
            ->recordClasses(fn (Approval $record): ?string => $record->isVoided() ? 'fi-approval-voided' : null)
            ->columns([
                ViewColumn::make('approver')
                    ->label(__('app.approval.column.approver'))
                    ->state(fn (Approval $record): array => [
                        'avatar' => $record->user?->avatarUrl(),
                        'name' => $record->user?->name ?? __('app.label.not_set'),
                        'sub' => collect([
                            $record->user?->department?->name,
                            $record->user?->position?->name,
                        ])->filter()->implode(' · ') ?: null,
                    ])
                    ->view('filament.tables.columns.person'),

                TextColumn::make('order')
                    ->label(__('app.label.step'))
                    ->badge()
                    ->color('gray')
                    ->state(fn (Approval $record): ?string => $record->isVoided() ? null : '#'.$record->order)
                    ->placeholder('—'),

                ViewColumn::make('status')
                    ->label(__('app.label.status'))
                    ->view('filament.tables.columns.approval-status'),

                TextColumn::make('comment')
                    ->label(__('app.approval.field.comment'))
                    ->wrap()
                    ->placeholder(__('app.label.not_set')),

                TextColumn::make('due_at')
                    ->label(__('app.approval.column.due'))
                    ->dateTime('d.m.Y H:i')
                    ->placeholder(__('app.label.not_set'))
                    ->color(fn (Approval $record): ?string => $record->isOverdue() ? 'danger' : null),

                TextColumn::make('acted_at')
                    ->label(__('app.approval.column.acted_at'))
                    ->dateTime('d.m.Y H:i')
                    ->placeholder(__('app.label.not_set')),
            ])
            ->recordActions([$this->detailsAction()])
            ->paginated(false)
            ->emptyStateHeading(__('app.approval.empty'))
            ->emptyStateIcon('heroicon-o-user-group');
    }

    protected function detailsAction(): Action
    {
        return Action::make('approverDetails')
            ->label(__('app.label.view_history'))
            ->icon('heroicon-o-eye')
            ->color('gray')
            ->modalHeading(fn (Approval $record): string => $record->user?->name ?? __('app.label.not_set'))
            ->modalDescription(fn (Approval $record): ?string => collect([
                $record->user?->department?->name,
                $record->user?->position?->name,
            ])->filter()->implode(' · ') ?: null)
            ->modalIcon('heroicon-o-user-circle')
            ->modalWidth(Width::TwoExtraLarge)
            ->modalContent(fn (Approval $record) => view('filament.approvals.approver-details', [
                'approval' => $record,
                'attempts' => $this->attemptsOf($record),
                'total' => $this->liveSteps(),
            ]))
            ->modalSubmitAction(false)
            ->modalCancelAction(false);
    }

    /** @return list<int> */
    protected function visibleApprovalIds(): array
    {
        $approvals = $this->record()?->approvals ?? collect();

        $live = $approvals->reject(fn (Approval $approval): bool => $approval->isVoided());
        $liveUserIds = $live->pluck('user_id')->all();

        $droppedOnly = $approvals
            ->filter(fn (Approval $approval): bool => $approval->isVoided() && ! in_array($approval->user_id, $liveUserIds, true))
            ->sortByDesc('id')
            ->unique('user_id');

        return $live->merge($droppedOnly)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /** @return Collection<int, Approval> */
    protected function attemptsOf(Approval $approval): Collection
    {
        return Approval::query()
            ->where('approvable_type', $approval->approvable_type)
            ->where('approvable_id', $approval->approvable_id)
            ->where('user_id', $approval->user_id)
            ->orderByDesc('round')
            ->orderByDesc('id')
            ->get();
    }

    protected function liveSteps(): int
    {
        return $this->record()?->activeApprovals()->pluck('order')->unique()->count() ?? 0;
    }

    protected function progressSummary(): string
    {
        $approvals = $this->record()?->activeApprovals() ?? collect();

        if ($approvals->isEmpty()) {
            return __('app.approval.sequential_hint');
        }

        $parts = [__('app.approval.progress', [
            'approved' => $approvals->where('status', ApprovalStatus::Approved)->count(),
            'total' => $approvals->count(),
        ])];

        if ($queued = $approvals->where('status', ApprovalStatus::Queued)->count()) {
            $parts[] = __('app.approval.in_queue', ['count' => $queued]);
        }

        $parts[] = __('app.approval.sequential_hint');

        return implode(' · ', $parts);
    }

    protected function record(): ?Requisition
    {
        if ($this->cachedRecord === null) {
            $this->cachedRecord = Requisition::query()->with('approvals')->find($this->requisitionId);
        }

        return $this->cachedRecord;
    }

    private ?Requisition $cachedRecord = null;
}
