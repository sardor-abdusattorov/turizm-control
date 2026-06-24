<?php

namespace App\Filament\Widgets\Dashboard;

use App\Enums\ContractStatus;
use App\Filament\Resources\Contracts\ContractResource;
use App\Filament\Support\WidgetPermission;
use App\Models\Contract;
use App\Models\ContractApprover;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class MyContractsInReviewWidget extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 6;

    protected ?string $pollingInterval = '60s';

    /**
     * The author's own contracts still working through the approval pipeline —
     * shows where each one is parked and whether the current approver has blown
     * the SLA. Replaces the manager half of the old overdue banner.
     */
    public static function canView(): bool
    {
        $user = auth()->user();

        return $user !== null
            && WidgetPermission::allows(static::class)
            && self::baseQuery((int) $user->id)->exists();
    }

    public function getTableHeading(): string
    {
        return __('app.dashboard.my_in_review_heading');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => self::baseQuery((int) auth()->id())
                ->with(['activeApprovers.user']))
            ->queryStringIdentifier('myContractsInReview')
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->defaultSort('updated_at', 'asc')
            ->columns([
                TextColumn::make('number')
                    ->label(__('app.label.contract_number'))
                    ->weight('semibold')
                    ->sortable(false),

                TextColumn::make('title')
                    ->label(__('app.label.contract_title'))
                    ->limit(40)
                    ->sortable(false),

                TextColumn::make('status')
                    ->label(__('app.label.status'))
                    ->badge()
                    ->formatStateUsing(fn (ContractStatus $state): string => $state->label())
                    ->color(fn (ContractStatus $state): string => $state->color()),

                TextColumn::make('holder')
                    ->label(__('app.dashboard.now_with'))
                    ->state(fn (Contract $record): string => $this->currentPending($record)?->user?->name ?? '—'),

                ViewColumn::make('due')
                    ->label(__('app.label.due'))
                    ->view('filament.components.sla-countdown')
                    ->state(fn (Contract $record) => $this->currentPending($record)?->due_at)
                    ->disabledClick(),
            ])
            ->recordUrl(fn (Contract $record) => ContractResource::getUrl('view', ['record' => $record]))
            ->recordActions([
                Action::make('open')
                    ->label(__('app.action.open_contract'))
                    ->icon('heroicon-m-arrow-right-circle')
                    ->color('primary')
                    ->url(fn (Contract $record) => ContractResource::getUrl('view', ['record' => $record])),
            ])
            ->emptyStateHeading(__('app.dashboard.my_in_review_empty'))
            ->emptyStateIcon('heroicon-o-clock');
    }

    /**
     * The approver currently sitting on the contract, read from the already
     * eager-loaded chain so the table doesn't fire a query per row.
     */
    private function currentPending(Contract $contract): ?ContractApprover
    {
        return $contract->activeApprovers
            ->firstWhere('status', ContractApprover::STATUS_PENDING);
    }

    private static function baseQuery(int $userId): Builder
    {
        return Contract::query()
            ->where('responsible_id', $userId)
            ->whereIn('status', array_map(
                fn (ContractStatus $status): string => $status->value,
                [
                    Contract::STATUS_IN_REVIEW,
                    Contract::STATUS_PENDING_DIRECTOR,
                    Contract::STATUS_IN_REVIEW_DIRECTOR,
                ],
            ));
    }
}
