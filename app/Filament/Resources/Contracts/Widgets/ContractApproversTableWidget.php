<?php

namespace App\Filament\Resources\Contracts\Widgets;

use App\Models\Contract;
use App\Models\ContractApprover;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * The full approval chain of a contract as a stock Filament table — every row
 * (queued, pending, approved, returned, rejected, invalidated, skipped) in the
 * order it happened, a chronological audit log. Embedded in the "approval
 * chain" modal opened from the contracts list, replacing the hand-rolled table.
 */
class ContractApproversTableWidget extends TableWidget
{
    public int $contractId;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $contract = Contract::query()->find($this->contractId);
        $isDraft = $contract?->status === Contract::STATUS_DRAFT;

        return $table
            ->heading(__('app.label.approval_chain'))
            ->query(fn (): Builder => ContractApprover::query()
                ->where('contract_id', $this->contractId)
                ->with(['user.department', 'user.position'])
                ->orderBy('id'))
            ->columns([
                TextColumn::make('user.name')
                    ->label(__('app.label.approver'))
                    ->weight('semibold')
                    ->placeholder(__('app.label.not_set'))
                    ->formatStateUsing(fn (?string $state, ContractApprover $record): string => ($state ?? __('app.label.not_set')).' · #'.$record->order)
                    ->description(fn (ContractApprover $record): ?string => $record->user
                        ? trim(($record->user->department?->name ?? '').($record->user->position?->name ? ' · '.$record->user->position->name : ''), ' ·') ?: null
                        : null),

                TextColumn::make('comment')
                    ->label(__('app.label.comment'))
                    ->placeholder(__('app.label.not_set'))
                    ->wrap()
                    // The system note ("cancelled — document was edited") rides
                    // under the human comment as a muted description.
                    ->description(fn (ContractApprover $record): ?string => $record->system_comment
                        ? __('app.label.system_note').': '.$record->systemNoteLabel()
                        : null),

                TextColumn::make('status')
                    ->label(__('app.label.status'))
                    ->badge()
                    ->formatStateUsing(fn (ContractApprover $record): string => $isDraft && $record->status === ContractApprover::STATUS_QUEUED
                        ? __('app.contract_approver.status.not_submitted')
                        : $record->displayStatus()->label())
                    ->color(fn (ContractApprover $record): string => $record->displayStatus()->color()),

                TextColumn::make('due_at')
                    ->label(__('app.label.due'))
                    ->state(fn (ContractApprover $record): ?string => $record->status === ContractApprover::STATUS_PENDING && $record->due_at
                        ? $record->due_at->format('d.m.Y H:i')
                        : null)
                    ->badge(fn (ContractApprover $record): bool => $record->isOverdue())
                    ->color(fn (ContractApprover $record): string => $record->isOverdue() ? 'danger' : 'gray')
                    ->placeholder(__('app.label.not_set')),

                TextColumn::make('acted_at')
                    ->label(__('app.label.acted_at'))
                    ->dateTime('d.m.Y H:i')
                    ->placeholder(__('app.label.not_set')),
            ])
            ->paginated(false)
            ->emptyStateHeading(__('app.helper.approval_chain_empty'))
            ->emptyStateIcon('heroicon-o-users');
    }
}
