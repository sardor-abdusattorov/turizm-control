<?php

namespace App\Filament\Widgets\Counterparty;

use App\Enums\PaymentStatus;
use App\Filament\Resources\Contracts\ContractResource;
use App\Filament\Resources\Projects\BaseProjectResource;
use App\Models\Contract;
use App\Support\Money;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class CounterpartyProjectsTableWidget extends TableWidget
{
    public ?int $contactId = null;

    public ?int $sponsorId = null;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table

            ->heading(null)
            ->query(fn (): Builder => $this->scopeCounterparty(Contract::query()
                ->visibleTo()
                ->where('status', '!=', Contract::STATUS_REJECTED->value)
                ->whereHas('contractType', fn (Builder $query) => $query->where('direction', 'income'))
                ->with(['project', 'currency'])))
            ->defaultSort('id', 'desc')
            ->recordUrl(fn (Contract $record): ?string => $record->project
                ? BaseProjectResource::resourceFor($record->project)::getUrl('view', ['record' => $record->project])
                : null)
            ->columns([
                TextColumn::make('project.name')
                    ->label(__('app.label.project_single'))
                    ->weight('semibold')
                    ->placeholder(__('app.label.not_set'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('number')
                    ->label(__('app.label.contract_number'))
                    ->url(fn (Contract $record): string => ContractResource::getUrl('view', ['record' => $record]))
                    ->sortable(),

                TextColumn::make('amount')
                    ->label(__('app.label.amount'))
                    ->formatStateUsing(fn (Contract $record): string => Money::format($record->amount).' '.($record->currency?->short_name ?? ''))
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('paid')
                    ->label(__('app.label.paid'))
                    ->state(fn (Contract $record): string => Money::format($record->paidAmount()))
                    ->alignEnd(),

                TextColumn::make('payment_status')
                    ->label(__('app.label.payment_status'))
                    ->badge()
                    ->formatStateUsing(fn (PaymentStatus $state): string => $state->label())
                    ->color(fn (PaymentStatus $state): string => $state->color()),
            ])
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->emptyStateHeading(__('app.message.no_projects_for_contact'));
    }

    private function scopeCounterparty(Builder $query): Builder
    {
        return $this->sponsorId !== null
            ? $query->where('sponsor_id', $this->sponsorId)
            : $query->where('contact_id', $this->contactId ?? 0);
    }
}
