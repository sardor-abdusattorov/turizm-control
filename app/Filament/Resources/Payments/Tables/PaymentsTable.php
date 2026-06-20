<?php

namespace App\Filament\Resources\Payments\Tables;

use App\Filament\Resources\Contracts\ContractResource;
use App\Filament\Resources\Payments\PaymentResource;
use App\Models\Payment;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PaymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('paid_at', 'desc')
            // Payments naturally belong to a contract, and the same contract
            // recurs across rows — grouping clusters them and the per-group
            // percent sum shows how much of each contract is settled at a glance.
            ->groups([
                Group::make('contract.number')
                    ->label(__('app.label.contract'))
                    ->titlePrefixedWithLabel(false)
                    ->collapsible(),
            ])
            ->defaultGroup('contract.number')
            // The page/grand totals would sum percentages across different
            // contracts (meaningless), so only the per-group summary is shown.
            ->summaries(pageCondition: false, allTableCondition: false)
            ->columns([
                TextColumn::make('contract.number')
                    ->label(__('app.label.contract_number'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('contract.title')
                    ->label(__('app.label.contract_title'))
                    ->searchable()
                    ->limit(40)
                    ->wrap(),

                TextColumn::make('percent')
                    ->label(__('app.label.percent'))
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 2, '.', ' ').'%')
                    ->summarize(
                        Sum::make()
                            ->label(__('app.label.total_paid'))
                            ->formatStateUsing(fn ($state): string => number_format((float) $state, 2, '.', ' ').'%'),
                    )
                    ->sortable(),

                TextColumn::make('paid_at')
                    ->label(__('app.label.paid_at'))
                    ->date('d.m.Y')
                    ->sortable(),

                ImageColumn::make('screenshot')
                    ->label(__('app.label.screenshot'))
                    ->disk('public')
                    ->height(40)
                    ->square(),

                TextColumn::make('creator.name')
                    ->label(__('app.label.created_by'))
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label(__('app.label.created_at'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('paid_at')
                    ->schema([
                        DatePicker::make('from')->label(__('app.label.from')),
                        DatePicker::make('until')->label(__('app.label.until')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('paid_at', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('paid_at', '<=', $date));
                    }),
            ])
            ->recordUrl(fn (Payment $record) => PaymentResource::getUrl('view', ['record' => $record]))
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make()->color('gray'),

                    Action::make('openContract')
                        ->label(__('app.action.open_contract'))
                        ->icon('heroicon-o-arrow-top-right-on-square')
                        ->color('gray')
                        ->url(fn (Payment $record) => $record->contract
                            ? ContractResource::getUrl('view', ['record' => $record->contract])
                            : null)
                        ->visible(fn (Payment $record): bool => $record->contract !== null),
                ])
                    ->icon('heroicon-m-ellipsis-vertical'),
            ]);
    }
}
