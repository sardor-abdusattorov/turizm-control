<?php

namespace App\Filament\Widgets\Dashboard;

use App\Enums\ContractStatus;
use App\Enums\PaymentStatus;
use App\Filament\Pages\Dashboard;
use App\Filament\Resources\Contracts\ContractResource;
use App\Models\Contract;
use App\Support\Money;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class ProjectParticipantsTableWidget extends TableWidget
{
    use HasWidgetShield {
        canView as shieldCanView;
    }
    use InteractsWithPageFilters;

    public ?string $kind = null;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return static::shieldCanView() && (auth()->user()?->can('view_any_project') ?? false);
    }

    public function table(Table $table): Table
    {
        return $table

            ->heading(null)
            ->query(fn (): Builder => Contract::query()
                ->visibleTo()
                ->where('project_id', $this->projectId() ?? 0)
                ->where('status', '!=', Contract::STATUS_REJECTED->value)
                ->whereHas('contractType', fn (Builder $query) => $query->where('direction', 'income'))
                ->when($this->kind === 'sponsors', fn (Builder $query) => $query->whereNotNull('sponsor_id'))
                ->when($this->kind === 'participants', fn (Builder $query) => $query->whereNull('sponsor_id'))
                ->with(['contact', 'sponsor', 'currency', 'contractType']))
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

                TextColumn::make('number')
                    ->label(__('app.label.contract_number'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('contractType.title')
                    ->label(__('app.label.contract_type_single'))
                    ->badge()
                    ->color(fn (Contract $record): string => $record->contractType?->direction?->color() ?? 'gray')
                    ->placeholder(__('app.label.not_set')),

                TextColumn::make('amount')
                    ->label(__('app.label.amount'))
                    ->formatStateUsing(fn (Contract $record): string => Money::format($record->amount).' '.($record->currency?->short_name ?? ''))
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('paid_percent')
                    ->label(__('app.label.paid'))
                    ->formatStateUsing(fn (?string $state): string => format_percent($state).'%')
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('payment_status')
                    ->label(__('app.label.payment_status'))
                    ->badge()
                    ->formatStateUsing(fn (PaymentStatus $state): string => $state->label())
                    ->color(fn (PaymentStatus $state): string => $state->color()),

                TextColumn::make('status')
                    ->label(__('app.label.status'))
                    ->badge()
                    ->formatStateUsing(fn (ContractStatus $state): string => $state->label())
                    ->color(fn (ContractStatus $state): string => $state->color()),
            ])
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->emptyStateHeading($this->projectId()
                ? ($this->kind === 'sponsors' ? __('app.message.no_sponsors') : __('app.message.no_participants'))
                : __('app.message.pulse_pick_project'));
    }

    protected function projectId(): ?string
    {
        return Dashboard::filterValue($this->pageFilters['projectId'] ?? null);
    }
}
