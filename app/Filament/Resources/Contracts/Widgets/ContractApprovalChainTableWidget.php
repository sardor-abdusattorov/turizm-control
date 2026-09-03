<?php

namespace App\Filament\Resources\Contracts\Widgets;

use App\Models\Contract;
use App\Models\ContractApprover;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity;

class ContractApprovalChainTableWidget extends TableWidget
{
    use HasWidgetShield;

    public int $contractId;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $contract = $this->contract();
        $isDraft = $contract->status === Contract::STATUS_DRAFT;
        $directorUserId = $contract->directorUser()?->id;

        return $table

            ->heading(null)
            ->columnManager(false)
            ->query(fn (): Builder => ContractApprover::query()
                ->whereKey($this->visibleApproverIds())
                ->with(['user.department', 'user.position'])

                ->orderByRaw('CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END', [
                    ContractApprover::STATUS_INVALIDATED->value,
                    ContractApprover::STATUS_SKIPPED->value,
                ])
                ->orderBy('order')
                ->orderBy('id'))
            ->columns([
                ViewColumn::make('approver')
                    ->label(__('app.label.approver'))
                    ->state(fn (ContractApprover $record): array => [
                        'avatar' => $record->user?->avatarUrl(),
                        'name' => $record->user?->name ?? __('app.label.not_set'),
                        'sub' => $record->user
                            ? (trim(($record->user->department?->name ?? '').($record->user->position?->name ? ' · '.$record->user->position->name : ''), ' ·') ?: null)
                            : null,
                    ])
                    ->view('filament.tables.columns.person'),

                TextColumn::make('order')
                    ->label(__('app.label.step'))
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (int $state): string => '#'.$state)

                    ->description(fn (ContractApprover $record): ?string => $directorUserId && $record->user_id === $directorUserId
                        ? __('app.label.final_sign_off')
                        : null),

                TextColumn::make('status')
                    ->label(__('app.label.status'))
                    ->badge()
                    ->formatStateUsing(fn (ContractApprover $record): string => $this->statusLabel($record, $isDraft))
                    ->color(fn (ContractApprover $record): string => $this->isDropped($record)
                        ? 'gray'
                        : $record->displayStatus()->color())
                    ->icon(fn (ContractApprover $record): string => $this->statusIcon($record)),

                ViewColumn::make('timing')
                    ->label(__('app.label.due'))

                    ->state(fn (ContractApprover $record): array => [
                        'acted' => $record->acted_at?->format('d.m.Y H:i'),
                        'due' => $record->acted_at === null && $record->status === ContractApprover::STATUS_PENDING
                            ? $record->due_at
                            : null,
                    ])
                    ->view('filament.tables.columns.approver-timing'),

                TextColumn::make('comment')
                    ->label(__('app.label.comment'))
                    ->wrap()
                    ->placeholder(__('app.label.not_set'))

                    ->description(fn (ContractApprover $record): ?string => $record->system_comment
                        ? __('app.label.system_note').': '.$record->systemNoteLabel()
                        : null),
            ])
            ->recordActions([
                $this->approverDetailsAction(),
            ])
            ->recordUrl(null)
            ->paginated(false)
            ->emptyStateHeading(__('app.label.no_approvers'))
            ->emptyStateIcon('heroicon-o-users');
    }

    private function approverDetailsAction(): Action
    {
        return Action::make('approverDetails')
            ->label(__('app.label.view_history'))
            ->icon('heroicon-o-eye')
            ->color('gray')
            ->modalHeading(fn (ContractApprover $record): string => $record->user?->name ?? '')
            ->modalDescription(fn (ContractApprover $record): ?string => $record->user
                ? (trim(($record->user->department?->name ?? '').($record->user->position?->name ? ' · '.$record->user->position->name : ''), ' ·') ?: null)
                : null)
            ->modalIcon('heroicon-o-user-circle')
            ->modalWidth('3xl')
            ->modalContent(fn (ContractApprover $record) => view(
                'filament.resources.contracts.widgets.approver-details',
                [
                    'record' => $this->contract(),
                    'userId' => (int) $record->user_id,
                    'activities' => $this->activitiesFor((int) $record->user_id),
                ],
            ))
            ->modalSubmitAction(false)
            ->modalCancelAction(false);
    }

    /** @return list<int> */
    private function visibleApproverIds(): array
    {
        $approvers = $this->contract()->approvers;

        $active = $approvers->reject(fn (ContractApprover $a): bool => $this->isDropped($a));
        $activeUserIds = $active->pluck('user_id')->all();

        $droppedOnly = $approvers
            ->filter(fn (ContractApprover $a): bool => $this->isDropped($a) && ! in_array($a->user_id, $activeUserIds, true))
            ->sortByDesc('id')
            ->unique('user_id');

        return $active->merge($droppedOnly)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    private function isDropped(ContractApprover $approver): bool
    {
        return in_array($approver->status, [
            ContractApprover::STATUS_INVALIDATED,
            ContractApprover::STATUS_SKIPPED,
        ], true);
    }

    private function statusLabel(ContractApprover $approver, bool $isDraft): string
    {
        if ($this->isDropped($approver)) {
            return __('app.label.no_longer_in_chain');
        }

        if ($isDraft && $approver->status === ContractApprover::STATUS_QUEUED) {
            return __('app.label.not_submitted');
        }

        return $approver->displayStatus()->label();
    }

    private function statusIcon(ContractApprover $approver): string
    {
        if ($this->isDropped($approver)) {
            return 'heroicon-m-minus-circle';
        }

        return match ($approver->displayStatus()) {
            ContractApprover::STATUS_APPROVED => 'heroicon-m-check-circle',
            ContractApprover::STATUS_REJECTED => 'heroicon-m-x-circle',
            ContractApprover::STATUS_PENDING => 'heroicon-m-clock',
            default => 'heroicon-m-minus-circle',
        };
    }

    /** @return Collection<int, Activity> */
    private function activitiesFor(int $userId): Collection
    {
        return Activity::query()
            ->where('subject_type', (new Contract)->getMorphClass())
            ->where('subject_id', $this->contractId)
            ->where('causer_id', $userId)
            ->latest()
            ->limit(60)
            ->get();
    }

    private function contract(): Contract
    {
        return $this->cachedContract ??= Contract::query()
            ->with(['approvers.user.department', 'approvers.user.position'])
            ->findOrFail($this->contractId);
    }

    private ?Contract $cachedContract = null;
}
