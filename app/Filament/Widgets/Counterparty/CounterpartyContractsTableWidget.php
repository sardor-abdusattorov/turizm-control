<?php

namespace App\Filament\Widgets\Counterparty;

use App\Enums\ContractStatus;
use App\Filament\Resources\Contracts\ContractResource;
use App\Models\Contract;
use App\Support\Money;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Every contract of a counterparty (contact OR sponsor) as a stock Filament
 * table — embedded on the contact and sponsor view pages with the same
 * visibleTo() scoping as the count badges. Pass exactly one of the two ids.
 */
class CounterpartyContractsTableWidget extends TableWidget
{
    public ?int $contactId = null;

    public ?int $sponsorId = null;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            // No heading: the tab label / modal title already names it.
            ->heading(null)
            ->query(fn (): Builder => $this->scopeCounterparty(Contract::query()
                ->visibleTo()
                ->with(['contractType', 'currency', 'project'])))
            ->defaultSort('id', 'desc')
            ->recordUrl(fn (Contract $record): string => ContractResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('number')
                    ->label(__('app.label.contract_number'))
                    ->weight('semibold')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('contractType.title')
                    ->label(__('app.label.contract_type_single'))
                    ->badge()
                    ->color(fn (Contract $record): string => $record->contractType?->direction?->color() ?? 'gray')
                    ->placeholder(__('app.label.not_set'))
                    ->visibleFrom('lg')
                    ->sortable(),

                TextColumn::make('project.name')
                    ->label(__('app.label.project_single'))
                    ->placeholder(__('app.label.not_set'))
                    ->searchable()
                    ->visibleFrom('md')
                    ->sortable(),

                TextColumn::make('amount')
                    ->label(__('app.label.amount'))
                    ->formatStateUsing(fn (Contract $record): string => Money::format($record->amount).' '.($record->currency?->short_name ?? ''))
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('status')
                    ->label(__('app.label.status'))
                    ->badge()
                    ->formatStateUsing(fn (ContractStatus $state): string => $state->label())
                    ->color(fn (ContractStatus $state): string => $state->color()),
            ])
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->emptyStateHeading(__('app.message.no_contracts_for_contact'));
    }

    private function scopeCounterparty(Builder $query): Builder
    {
        return $this->sponsorId !== null
            ? $query->where('sponsor_id', $this->sponsorId)
            : $query->where('contact_id', $this->contactId ?? 0);
    }
}
