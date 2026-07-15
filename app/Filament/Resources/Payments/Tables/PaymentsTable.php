<?php

namespace App\Filament\Resources\Payments\Tables;

use App\Enums\PaymentStatus;
use App\Exports\PaymentsExport;
use App\Filament\Resources\Contracts\ContractResource;
use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Support\ExportPermission;
use App\Models\Payment;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Facades\Excel;

class PaymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('paid_at', 'desc')
            ->groups([
                Group::make('contract.number')
                    ->label(__('app.label.contract'))
                    ->titlePrefixedWithLabel(false)
                    ->collapsible(),
            ])
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
                    ->disk('local')
                    ->imageHeight(40)
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
                SelectFilter::make('contract_id')
                    ->label(__('app.label.contract'))
                    ->relationship('contract', 'number')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('contract_payment_status')
                    ->label(__('app.label.payment_status'))
                    ->options(PaymentStatus::options())
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when(
                            $data['value'] ?? null,
                            fn (Builder $q, $value) => $q->whereHas(
                                'contract',
                                fn (Builder $c) => $c->where('payment_status', $value),
                            ),
                        )),

                SelectFilter::make('created_by')
                    ->label(__('app.label.created_by'))
                    ->options(fn () => User::query()
                        ->where('status', User::STATUS_ACTIVE)
                        ->orderBy('name')
                        ->pluck('name', 'id'))
                    ->searchable(),

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
            ->filtersFormColumns(2)
            ->recordUrl(fn (Payment $record) => PaymentResource::getUrl('view', ['record' => $record]))
            ->headerActions([
                Action::make('exportXlsx')
                    ->label(__('app.action.export_xlsx'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->visible(fn (): bool => ExportPermission::allows('export_payment'))
                    ->action(fn ($livewire) => Excel::download(
                        new PaymentsExport($livewire->getFilteredTableQuery()),
                        'payments-'.now()->format('Y-m-d').'.xlsx',
                    )),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make()->color('gray'),

                    EditAction::make()->color('gray'),

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
