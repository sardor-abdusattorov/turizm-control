<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Payments\PaymentResource;
use App\Models\Payment;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class LatestPaymentsWidget extends TableWidget
{
    protected int|string|array $columnSpan = ['default' => 'full', 'md' => 1];

    protected static ?int $sort = 4;

    public function getTableHeading(): ?string
    {
        return __('app.stats.latest_payments_heading');
    }

    public static function canView(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $user->hasAnyRole(['accountant', 'director', 'super_admin']);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Payment::query()
                ->visibleTo()
                ->with('contract', 'creator')
                ->latest('paid_at')
                ->latest('id')
                ->limit(5))
            ->paginated(false)
            ->recordUrl(fn (Payment $record) => PaymentResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('contract.number')
                    ->label(__('app.label.contract_number'))
                    ->searchable(false)
                    ->sortable(false),

                TextColumn::make('percent')
                    ->label(__('app.label.percent'))
                    ->formatStateUsing(fn ($state): string => rtrim(rtrim(number_format((float) $state, 2, '.', ''), '0'), '.').'%')
                    ->badge()
                    ->color('success'),

                TextColumn::make('paid_at')
                    ->label(__('app.label.paid_at'))
                    ->date('d.m.Y'),

                TextColumn::make('creator.name')
                    ->label(__('app.label.created_by'))
                    ->limit(20)
                    ->toggleable(),
            ])
            ->emptyStateHeading(__('app.label.no_payments_yet'))
            ->emptyStateIcon('heroicon-o-banknotes');
    }
}
