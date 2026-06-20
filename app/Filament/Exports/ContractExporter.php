<?php

namespace App\Filament\Exports;

use App\Models\Contract;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;

class ContractExporter extends Exporter
{
    protected static ?string $model = Contract::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label('№'),

            ExportColumn::make('number')
                ->label(__('app.label.contract_number')),

            ExportColumn::make('title')
                ->label(__('app.label.contract_title')),

            ExportColumn::make('contact.name')
                ->label(__('app.label.contact_single'))
                ->formatStateUsing(fn ($state): ?string => is_array($state)
                    ? ($state[app()->getLocale()] ?? $state['ru'] ?? reset($state) ?: null)
                    : $state),

            ExportColumn::make('amount')
                ->label(__('app.label.amount'))
                ->formatStateUsing(fn ($state): string => number_format((float) $state, 2, '.', ' ')),

            ExportColumn::make('currency.short_name')
                ->label(__('app.label.currency_single')),

            ExportColumn::make('status')
                ->label(__('app.label.status'))
                ->formatStateUsing(fn ($state): string => $state?->label() ?? ''),

            ExportColumn::make('payment_status')
                ->label(__('app.label.payment_status'))
                ->formatStateUsing(fn ($state): string => $state?->label() ?? ''),

            ExportColumn::make('paid_percent')
                ->label(__('app.label.paid_percent'))
                ->formatStateUsing(fn ($state): string => rtrim(rtrim(number_format((float) $state, 2, '.', ''), '0'), '.').'%'),

            ExportColumn::make('responsible.name')
                ->label(__('app.label.responsible')),

            ExportColumn::make('signed_at')
                ->label(__('app.label.signing_date'))
                ->formatStateUsing(fn ($state): ?string => $state?->format('d.m.Y')),

            ExportColumn::make('created_at')
                ->label(__('app.label.created_at'))
                ->formatStateUsing(fn ($state): ?string => $state?->format('d.m.Y H:i')),
        ];
    }

    /**
     * Eager-load the relations every column needs so the export doesn't
     * fall into N+1 once it starts streaming thousands of rows.
     */
    public static function modifyQuery(Builder $query): Builder
    {
        return $query->with(['contact', 'currency', 'responsible']);
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = __('app.export.completed_body', [
            'count' => Number::format($export->successful_rows),
        ]);

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.__('app.export.failed_rows', [
                'count' => Number::format($failedRowsCount),
            ]);
        }

        return $body;
    }
}
