<?php

namespace App\Filament\Widgets;

use App\Enums\ApprovalStatus;
use App\Models\Approval;
use App\Models\Requisition;
use Filament\Actions\Action;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The whole audit trail of one document's chain: the round that is running
 * reads first, the rounds it replaced sit underneath as history.
 */
class ApprovalsTimelineWidget extends TableWidget
{
    public int $requisitionId;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading(null)
            ->columnManager(false)
            ->query(fn (): Builder => Approval::query()
                ->where('approvable_type', (new Requisition)->getMorphClass())
                ->where('approvable_id', $this->requisitionId)
                ->with(['user.department', 'user.position'])
                ->orderByDesc('round')
                ->orderBy('order')
                ->orderBy('id'))
            ->description(fn (): string => $this->progressSummary())
            // A voided round recedes into history rather than competing with
            // the round that is actually running.
            ->recordClasses(fn (Approval $record): ?string => $record->isVoided() ? 'fi-approval-voided' : null)
            ->columns([
                ViewColumn::make('approver')
                    ->label(__('app.approval.column.approver'))
                    ->state(fn (Approval $record): array => [
                        'avatar' => $record->user?->avatarUrl(),
                        'name' => trim(($record->user?->name ?? __('app.label.not_set')).' · #'.$record->order),
                        'sub' => collect([
                            $record->user?->department?->name,
                            $record->user?->position?->name,
                        ])->filter()->implode(' · ') ?: null,
                    ])
                    ->view('filament.tables.columns.person'),

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

    /**
     * Everything the record holds about one person on this document — the row
     * itself plus every other round they were asked in.
     */
    protected function detailsAction(): Action
    {
        return Action::make('approverDetails')
            ->hiddenLabel()
            ->icon('heroicon-m-eye')
            ->color('gray')
            ->tooltip(__('app.label.details'))
            ->modalHeading(fn (Approval $record): string => $record->user?->name ?? __('app.label.not_set'))
            ->modalWidth(Width::TwoExtraLarge)
            ->modalContent(fn (Approval $record) => view('filament.approvals.approver-details', [
                'approval' => $record,
                'attempts' => $this->attemptsOf($record),
                'total' => $this->liveSteps(),
            ]))
            ->modalSubmitAction(false)
            ->modalCancelAction(false);
    }

    /**
     * @return Collection<int, Approval>
     */
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

    /** Steps in the round that is running — voided rounds must not inflate it. */
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
        return Requisition::query()->with('approvals')->find($this->requisitionId);
    }
}
