<?php

namespace App\Filament\Widgets\Dashboard;

use App\Filament\Resources\Contracts\ContractResource;
use App\Models\Contract;
use App\Models\ContractApprover;
use App\Services\Dashboard\DashboardContext;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class MyApprovalQueueWidget extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        $context = app(DashboardContext::class);

        return $context->isApprover() && $context->awaitingMe()->isNotEmpty();
    }

    public function getTableHeading(): string
    {
        return __('app.dashboard.awaiting_my_signature');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Contract::query()
                ->awaitingApprovalBy()
                ->with(['contact', 'currency', 'activeApprovers']))
            ->paginated(false)
            ->defaultSort('updated_at', 'asc')
            ->columns([
                TextColumn::make('number')
                    ->label(__('app.label.contract_number'))
                    ->weight('semibold')
                    ->sortable(false),

                TextColumn::make('title')
                    ->label(__('app.label.contract_title'))
                    ->limit(45)
                    ->sortable(false),

                TextColumn::make('contact.name')
                    ->label(__('app.label.contact_single'))
                    ->limit(28)
                    ->toggleable(),

                TextColumn::make('amount')
                    ->label(__('app.label.amount'))
                    ->formatStateUsing(fn ($state, Contract $record): string => number_format((float) $state, 0, '.', ' ').' '.($record->currency?->short_name ?? ''))
                    ->sortable(false),

                TextColumn::make('due')
                    ->label(__('app.label.due'))
                    ->state(fn (Contract $record): string => $this->dueLabel($record))
                    ->badge()
                    ->color(fn (Contract $record): string => $this->myRow($record)?->isOverdue() ? 'danger' : 'warning'),
            ])
            ->recordUrl(fn (Contract $record) => ContractResource::getUrl('view', ['record' => $record]))
            ->recordActions([
                Action::make('review')
                    ->label(__('app.action.open_contract'))
                    ->icon('heroicon-m-arrow-right-circle')
                    ->color('primary')
                    ->url(fn (Contract $record) => ContractResource::getUrl('view', ['record' => $record])),
            ])
            ->emptyStateHeading(__('app.dashboard.queue_empty'))
            ->emptyStateIcon('heroicon-o-check-circle');
    }

    private function myRow(Contract $contract): ?ContractApprover
    {
        return app(DashboardContext::class)->myApproverRow($contract);
    }

    private function dueLabel(Contract $contract): string
    {
        $due = $this->myRow($contract)?->due_at;

        if (! $due) {
            return __('app.label.not_set');
        }

        return $due->isPast()
            ? __('app.dashboard.overdue_by', ['time' => $due->diffForHumans(now(), ['parts' => 1, 'syntax' => CarbonInterface::DIFF_ABSOLUTE])])
            : __('app.dashboard.due_in', ['time' => $due->diffForHumans(now(), ['parts' => 1, 'syntax' => CarbonInterface::DIFF_ABSOLUTE])]);
    }
}
