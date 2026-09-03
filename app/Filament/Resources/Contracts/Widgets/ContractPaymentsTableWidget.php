<?php

namespace App\Filament\Resources\Contracts\Widgets;

use App\Models\Payment;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class ContractPaymentsTableWidget extends TableWidget
{
    use HasWidgetShield;

    public int $contractId;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table

            ->heading(null)
            ->columnManager(false)
            ->query(fn (): Builder => Payment::query()
                ->where('contract_id', $this->contractId)
                ->with('creator')
                ->orderBy('paid_at')
                ->orderBy('id'))
            ->columns([
                TextColumn::make('percent')
                    ->label(__('app.label.percent'))
                    ->badge()
                    ->color('success')
                    ->icon('heroicon-m-check-circle')
                    ->formatStateUsing(fn ($state): string => format_percent((float) $state).'%'),

                TextColumn::make('paid_at')
                    ->label(__('app.label.paid_at'))
                    ->date('d.m.Y')
                    ->placeholder(__('app.label.not_set')),

                TextColumn::make('creator.name')
                    ->label(__('app.label.created_by'))
                    ->placeholder(__('app.label.system'))
                    ->description(fn (Payment $record): ?string => $record->created_at?->format('d.m.Y H:i')),

                ViewColumn::make('screenshots')
                    ->label(__('app.label.attachments'))
                    ->state(fn (Payment $record): array => $record->screenshotFiles())
                    ->view('filament.tables.columns.file-links'),
            ])
            ->paginated(false)
            ->emptyStateHeading(__('app.label.no_payments_yet'))
            ->emptyStateIcon('heroicon-o-banknotes');
    }
}
