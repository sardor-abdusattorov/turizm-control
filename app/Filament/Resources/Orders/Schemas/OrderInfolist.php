<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Models\Order;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

class OrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                View::make('filament.resources.orders.components.file-preview')
                    ->visible(fn (?Order $record): bool => (bool) $record?->fileExists()),

                Section::make(__('app.label.basic_information'))
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 2])
                            ->schema([
                                TextEntry::make('orderType.title')
                                    ->label(__('app.label.order_type_single'))
                                    ->badge(),

                                TextEntry::make('deadline_at')
                                    ->label(__('app.label.deadline'))
                                    ->date('d.m.Y')
                                    ->placeholder('—'),

                                TextEntry::make('title')
                                    ->label(__('app.label.title'))
                                    ->columnSpanFull(),

                                TextEntry::make('description')
                                    ->label(__('app.label.description'))
                                    ->columnSpanFull()
                                    ->placeholder('—'),

                                TextEntry::make('creator.name')
                                    ->label(__('app.label.created_by'))
                                    ->placeholder('—'),

                                IconEntry::make('status')
                                    ->label(__('app.label.status'))
                                    ->boolean(),
                            ]),
                    ]),
            ]);
    }
}
