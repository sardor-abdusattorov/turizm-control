<?php

namespace App\Filament\Resources\Contracts\Widgets;

use App\Enums\ContractApproverStatus;
use App\Models\Contract;
use App\Models\ContractApprover;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class ContractApproversTableWidget extends TableWidget
{
    public int $contractId;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $contract = Contract::query()->find($this->contractId);
        $isDraft = $contract?->status === Contract::STATUS_DRAFT;

        return $table

            ->heading(null)
            ->columnManager(false)
            ->query(fn (): Builder => ContractApprover::query()
                ->where('contract_id', $this->contractId)
                ->with(['user.department', 'user.position'])
                ->orderBy('id'))
            ->columns([
                ViewColumn::make('approver')
                    ->label(__('app.label.approver'))
                    ->state(fn (ContractApprover $record): array => [
                        'avatar' => $record->user?->avatarUrl(),
                        'name' => ($record->user?->name ?? __('app.label.not_set')).' · #'.$record->order,
                        'sub' => $record->user
                            ? (trim(($record->user->department?->name ?? '').($record->user->position?->name ? ' · '.$record->user->position->name : ''), ' ·') ?: null)
                            : null,
                    ])
                    ->view('filament.tables.columns.person'),

                TextColumn::make('comment')
                    ->label(__('app.label.comment'))
                    ->placeholder(__('app.label.not_set'))
                    ->wrap()

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
            ->filters([

                SelectFilter::make('status')
                    ->label(__('app.label.status'))
                    ->options(ContractApproverStatus::options())
                    ->query(function (Builder $query, array $data) {
                        $status = $data['value'] ?? null;

                        if (! $status) {
                            return $query;
                        }

                        return $query->where(function (Builder $query) use ($status): void {
                            $query->where('status', $status)
                                ->orWhere(function (Builder $query) use ($status): void {
                                    $query->where('status', ContractApproverStatus::Invalidated->value)
                                        ->where('original_status', $status);
                                });
                        });
                    }),
            ])
            ->paginated(false)
            ->emptyStateHeading(__('app.helper.approval_chain_empty'))
            ->emptyStateIcon('heroicon-o-users');
    }
}
