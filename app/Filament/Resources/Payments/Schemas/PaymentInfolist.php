<?php

namespace App\Filament\Resources\Payments\Schemas;

use App\Filament\Resources\Contracts\ContractResource;
use App\Models\Payment;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PaymentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make()
                    ->columns(2)
                    ->schema([
                        TextEntry::make('contract.number')
                            ->label(__('app.label.contract'))
                            ->formatStateUsing(fn (Payment $record): string => trim(
                                ($record->contract?->number ?? '').' · '.($record->contract?->title ?? ''),
                                ' ·',
                            ))
                            ->url(fn (Payment $record): ?string => $record->contract
                                ? ContractResource::getUrl('view', ['record' => $record->contract])
                                : null)
                            ->columnSpanFull(),

                        TextEntry::make('percent')
                            ->label(__('app.label.percent'))
                            ->formatStateUsing(fn ($state): string => number_format((float) $state, 2, '.', ' ').'%'),

                        TextEntry::make('paid_at')
                            ->label(__('app.label.paid_at'))
                            ->date('d.m.Y'),

                        TextEntry::make('creator.name')
                            ->label(__('app.label.created_by')),

                        TextEntry::make('created_at')
                            ->label(__('app.label.created_at'))
                            ->dateTime('d.m.Y H:i'),

                        ImageEntry::make('screenshot')
                            ->label(__('app.label.screenshot'))
                            ->disk('local')
                            ->visibility('private')
                            ->height(280)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
