<?php

namespace App\Filament\Exports;

use App\Models\Contact;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class ContactExporter extends Exporter
{
    protected static ?string $model = Contact::class;

    public static function getColumns(): array
    {
        $localized = static fn ($state): ?string => is_array($state)
            ? ($state[app()->getLocale()] ?? $state['ru'] ?? reset($state) ?: null)
            : $state;

        return [
            ExportColumn::make('id')
                ->label('№'),

            ExportColumn::make('type')
                ->label(__('app.label.contact_type'))
                ->formatStateUsing(fn (?string $state): string => match ($state) {
                    Contact::TYPE_LEGAL => __('app.contact.type.legal'),
                    Contact::TYPE_INDIVIDUAL => __('app.contact.type.individual'),
                    default => '',
                }),

            ExportColumn::make('name')
                ->label(__('app.label.contact_name'))
                ->formatStateUsing($localized),

            ExportColumn::make('legal_form')
                ->label(__('app.label.legal_form')),

            ExportColumn::make('inn')
                ->label(__('app.label.inn'))
                // Force text by prefixing the apostrophe so leading zeros are
                // not stripped when the file opens in Excel.
                ->prefix("'"),

            ExportColumn::make('pinfl')
                ->label(__('app.label.pinfl'))
                ->prefix("'"),

            ExportColumn::make('oked')
                ->label('OKED')
                ->prefix("'"),

            ExportColumn::make('address')
                ->label(__('app.label.address'))
                ->formatStateUsing($localized),

            ExportColumn::make('phone')
                ->label(__('app.label.phone')),

            ExportColumn::make('email')
                ->label(__('app.label.email')),

            ExportColumn::make('contact_person')
                ->label(__('app.label.contact_person')),

            ExportColumn::make('director_name')
                ->label(__('app.label.director_name')),

            ExportColumn::make('bank_account')
                ->label(__('app.label.bank_account'))
                ->prefix("'"),

            ExportColumn::make('bank_name')
                ->label(__('app.label.bank_name')),

            ExportColumn::make('mfo')
                ->label(__('app.label.mfo'))
                ->prefix("'"),

            ExportColumn::make('status')
                ->label(__('app.label.status'))
                ->formatStateUsing(fn ($state): string => $state ? __('app.label.active') : __('app.label.inactive')),

            ExportColumn::make('created_at')
                ->label(__('app.label.created_at'))
                ->formatStateUsing(fn ($state): ?string => $state?->format('d.m.Y H:i')),
        ];
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
