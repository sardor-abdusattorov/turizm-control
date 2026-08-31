<?php

namespace App\Filament\Resources\Payments\Tables;

use App\Enums\PaymentStatus;
use App\Enums\PaymentSubject;
use App\Exports\PaymentsExport;
use App\Filament\Resources\Contracts\ContractResource;
use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Resources\Projects\BaseProjectResource;
use App\Filament\Support\ExportPermission;
use App\Filament\Support\ExportXlsxAction;
use App\Models\Payment;
use App\Models\User;
use App\Support\Money;
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

            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['contract', 'project', 'currency', 'creator']))
            ->defaultSort('paid_at', 'desc')
            ->groups([
                Group::make('contract.number')
                    ->label(__('app.label.contract'))
                    ->titlePrefixedWithLabel(false)
                    ->collapsible(),

                Group::make('project.name')
                    ->label(__('app.label.project_single'))
                    ->titlePrefixedWithLabel(false)
                    ->collapsible(),
            ])
            ->summaries(pageCondition: false, allTableCondition: false)
            ->columns([
                TextColumn::make('subject')
                    ->label(__('app.label.payment_subject'))
                    ->badge()
                    ->state(fn (Payment $record): PaymentSubject => $record->isDirect()
                        ? PaymentSubject::Project
                        : PaymentSubject::Contract)
                    ->color(fn (PaymentSubject $state): string => $state->color())
                    ->icon(fn (PaymentSubject $state): string => $state->icon())
                    ->formatStateUsing(fn (PaymentSubject $state): string => $state->label()),

                TextColumn::make('contract.number')
                    ->label(__('app.label.payment_object'))
                    ->state(fn (Payment $record): string => $record->subjectLabel())
                    ->description(fn (Payment $record): ?string => $record->isDirect()
                        ? $record->purpose
                        : $record->contract?->title)
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->extraCellAttributes(['style' => 'min-width: 20rem']),

                TextColumn::make('percent')
                    ->label(__('app.label.percent'))
                    ->placeholder('—')
                    ->formatStateUsing(fn ($state): string => format_percent($state).'%')
                    ->summarize(
                        Sum::make()
                            ->label(__('app.label.total_paid'))
                            ->formatStateUsing(fn ($state): string => format_percent($state).'%'),
                    )
                    ->sortable(),

                TextColumn::make('amount')
                    ->label(__('app.label.amount'))
                    ->placeholder('—')
                    ->formatStateUsing(fn (?string $state, Payment $record): string => Money::format($state)
                        .' '.($record->currency?->short_name ?? ''))
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('paid_at')
                    ->label(__('app.label.paid_at'))
                    ->date('d.m.Y')
                    ->sortable(),

                ImageColumn::make('screenshots')
                    ->label(__('app.label.screenshot'))

                    ->state(fn (Payment $record): array => array_values(array_filter(
                        $record->screenshots ?? [],
                        fn (string $path): bool => ! Payment::isPdf($path),
                    )))
                    ->disk('local')
                    ->imageHeight(40)
                    ->square()
                    ->stacked()
                    ->limit(3),

                TextColumn::make('creator.name')
                    ->label(__('app.label.created_by'))
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label(__('app.label.created_at'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('subject')
                    ->label(__('app.label.payment_subject'))
                    ->options(PaymentSubject::options())
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when(
                            $data['value'] ?? null,
                            fn (Builder $q, string $value) => $value === PaymentSubject::Project->value
                                ? $q->whereNull('contract_id')
                                : $q->whereNotNull('contract_id'),
                        )),

                SelectFilter::make('contract_id')
                    ->label(__('app.label.contract'))
                    ->relationship('contract', 'number')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('project_id')
                    ->label(__('app.label.project_single'))
                    ->relationship('project', 'name')
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
                ExportXlsxAction::make()
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

                    Action::make('openProject')
                        ->label(__('app.action.open_project'))
                        ->icon('heroicon-o-arrow-top-right-on-square')
                        ->color('gray')
                        ->url(fn (Payment $record) => $record->project
                            ? BaseProjectResource::urlFor($record->project)
                            : null)
                        ->visible(fn (Payment $record): bool => $record->project !== null),
                ])
                    ->icon('heroicon-m-ellipsis-vertical'),
            ]);
    }
}
