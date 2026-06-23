<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Support\WidgetPermission;
use App\Models\Payment;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class LatestPaymentsWidget extends TableWidget
{
    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 1];

    protected static ?int $sort = 8;

    public function getTableHeading(): ?string
    {
        return __('app.stats.latest_payments_heading');
    }

    public static function canView(): bool
    {
        return WidgetPermission::allows(static::class);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Payment::query()
                ->visibleTo()
                ->with('contract', 'creator')
                ->latest('paid_at')
                ->latest('id'))
            ->queryStringIdentifier('latestPayments')
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->recordUrl(fn (Payment $record) => PaymentResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('contract.number')
                    ->label(__('app.label.contract_number'))
                    ->searchable(false)
                    ->sortable(false)
                    ->description(fn (Payment $record): ?string => $record->creator?->name),

                TextColumn::make('percent')
                    ->label(__('app.label.percent'))
                    ->formatStateUsing(fn ($state): string => format_percent((float) $state).'%')
                    ->badge()
                    ->color('success'),

                TextColumn::make('paid_at')
                    ->label(__('app.label.paid_at'))
                    ->date('d.m.Y'),
            ])
            ->emptyStateHeading(__('app.label.no_payments_yet'))
            ->emptyStateIcon('heroicon-o-banknotes');
    }
}
