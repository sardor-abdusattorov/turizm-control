<?php

namespace App\Filament\Resources\Contacts\Widgets;

use App\Models\BankAccount;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class ContactBankAccountsTableWidget extends TableWidget
{
    public int $contactId;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table

            ->heading(null)
            ->columnManager(false)
            ->query(fn (): Builder => BankAccount::query()
                ->where('contact_id', $this->contactId)
                ->with('currency')
                ->orderBy('sort'))
            ->columns([
                TextColumn::make('currency.short_name')
                    ->label(__('app.label.currency_single'))
                    ->badge()
                    ->color('gray')
                    ->placeholder(__('app.label.bank_account_any_currency')),

                TextColumn::make('account_number')
                    ->label(__('app.label.bank_account'))
                    ->weight('semibold')
                    ->fontFamily('mono')
                    ->copyable(),

                TextColumn::make('bank_name')
                    ->label(__('app.label.bank_name'))
                    ->wrap()
                    ->description(fn (BankAccount $record): ?string => $record->bank_address ?: null)
                    ->placeholder(__('app.label.not_set')),

                TextColumn::make('mfo')
                    ->label(__('app.label.mfo'))
                    ->placeholder(__('app.label.not_set')),

                TextColumn::make('swift')
                    ->label(__('app.label.swift'))
                    ->placeholder(__('app.label.not_set')),
            ])
            ->paginated(false)
            ->emptyStateHeading(__('app.message.no_bank_accounts'))
            ->emptyStateIcon('heroicon-o-building-library');
    }
}
