<?php

namespace App\Filament\Widgets\Dashboard;

use App\Filament\Resources\Contracts\ContractResource;
use App\Filament\Support\WidgetPermission;
use App\Models\Contract;
use App\Models\ContractApprover;
use App\Services\Dashboard\DashboardContext;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
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

        return WidgetPermission::allows(static::class)
            && $context->isApprover()
            && $context->awaitingMe()->isNotEmpty();
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
            ->queryStringIdentifier('myApprovalQueue')
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->poll('60s')
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

                ViewColumn::make('due')
                    ->label(__('app.label.due'))
                    ->view('filament.components.sla-countdown')
                    ->state(fn (Contract $record) => $this->myRow($record)?->due_at)
                    ->disabledClick(),
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
}
