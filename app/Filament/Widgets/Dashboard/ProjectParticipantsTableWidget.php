<?php

namespace App\Filament\Widgets\Dashboard;

use App\Enums\PaymentStatus;
use App\Filament\Pages\Dashboard;
use App\Filament\Resources\Contracts\ContractResource;
use App\Models\Contract;
use App\Support\Money;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Participants of a project — the counterparties of its income contracts
 * (fees + sponsorship), with the payment state of each deal. Lives on the
 * project view page's «Участники» tab; the dashboard folds the same rows
 * into its single contracts table.
 */
class ProjectParticipantsTableWidget extends TableWidget
{
    use InteractsWithPageFilters;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->can('view_any_project') ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('app.label.participants'))
            ->query(fn (): Builder => Contract::query()
                ->visibleTo()
                ->where('project_id', $this->projectId() ?? 0)
                ->where('status', '!=', Contract::STATUS_REJECTED->value)
                ->whereHas('contractType', fn (Builder $query) => $query->where('direction', 'income'))
                ->with(['contact', 'sponsor', 'currency']))
            ->defaultSort('amount', 'desc')
            ->recordUrl(fn (Contract $record): string => ContractResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('counterparty')
                    ->label(__('app.label.counterparty'))
                    ->weight('semibold')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query
                        ->where(fn (Builder $q) => $q
                            ->whereHas('contact', fn (Builder $c) => $c->where('name', 'like', "%{$search}%"))
                            ->orWhereHas('sponsor', fn (Builder $c) => $c->where('name', 'like', "%{$search}%"))))
                    ->state(fn (Contract $record): string => $record->counterparty()?->name ?? __('app.label.not_set')),

                TextColumn::make('amount')
                    ->label(__('app.label.amount'))
                    ->formatStateUsing(fn (Contract $record): string => Money::format($record->amount).' '.($record->currency?->short_name ?? ''))
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('payment_status')
                    ->label(__('app.label.payment_status'))
                    ->badge()
                    ->formatStateUsing(fn (PaymentStatus $state): string => $state->label())
                    ->color(fn (PaymentStatus $state): string => $state->color()),
            ])
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->emptyStateHeading($this->projectId()
                ? __('app.message.no_participants')
                : __('app.message.pulse_pick_project'));
    }

    protected function projectId(): ?string
    {
        return Dashboard::filterValue($this->pageFilters['projectId'] ?? null);
    }
}
