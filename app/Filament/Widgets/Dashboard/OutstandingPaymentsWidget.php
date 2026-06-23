<?php

namespace App\Filament\Widgets\Dashboard;

use App\Filament\Resources\Contracts\ContractResource;
use App\Models\Contract;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class OutstandingPaymentsWidget extends TableWidget
{
    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 1];

    protected static ?int $sort = 7;

    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole([
            'accountant',
            'director',
            'super_admin',
        ]) ?? false;
    }

    public function getTableHeading(): string
    {
        return __('app.dashboard.outstanding_heading');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Contract::query()
                ->visibleTo()
                ->acceptingPayments()
                ->with(['contact', 'currency'])
                ->orderByRaw('amount * (100 - paid_percent) DESC'))
            ->queryStringIdentifier('outstandingPayments')
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->recordUrl(fn (Contract $record) => ContractResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('number')
                    ->label(__('app.label.contract_number'))
                    ->weight('semibold')
                    ->sortable(false)
                    ->description(fn (Contract $record): ?string => $record->contact?->name),

                TextColumn::make('paid_percent')
                    ->label(__('app.label.percent'))
                    ->badge()
                    ->formatStateUsing(fn ($state): string => format_percent((float) $state).'%')
                    ->color(fn ($state): string => (float) $state > 0 ? 'warning' : 'gray'),

                TextColumn::make('remaining')
                    ->label(__('app.dashboard.remaining'))
                    ->alignEnd()
                    ->weight('semibold')
                    ->state(fn (Contract $record): string => number_format(
                        (float) $record->amount * (100 - (float) $record->paid_percent) / 100,
                        0,
                        '.',
                        ' ',
                    ).' '.($record->currency?->short_name ?? '')),
            ])
            ->emptyStateHeading(__('app.dashboard.outstanding_empty'))
            ->emptyStateIcon('heroicon-o-check-circle');
    }
}
