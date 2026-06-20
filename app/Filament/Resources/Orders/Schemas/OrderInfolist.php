<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Models\Order;
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
            ->columns(['default' => 1, 'lg' => 3])
            ->components([
                // Hero strip — keeps the page in the same look as the
                // View contract page (title + status pill on the right).
                View::make('filament.resources.orders.components.hero')
                    ->columnSpanFull(),

                // Main column: file preview card + description card.
                Section::make()
                    ->hiddenLabel()
                    ->columnSpan(['default' => 1, 'lg' => 2])
                    ->schema([
                        View::make('filament.resources.orders.components.file-preview')
                            ->visible(fn (?Order $record): bool => (bool) $record?->fileExists()),

                        Section::make(__('app.label.description'))
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                TextEntry::make('description')
                                    ->hiddenLabel()
                                    ->prose()
                                    ->placeholder(__('app.label.not_set')),
                            ]),
                    ]),

                // Sidebar: metadata table.
                Section::make(__('app.label.basic_information'))
                    ->icon('heroicon-o-clipboard-document-list')
                    ->columnSpan(['default' => 1, 'lg' => 1])
                    ->schema([
                        Grid::make(1)->schema([
                            TextEntry::make('orderType.title')
                                ->label(__('app.label.order_type_single'))
                                ->badge()
                                ->icon('heroicon-o-tag'),

                            TextEntry::make('deadline_at')
                                ->label(__('app.label.deadline'))
                                ->date('d.m.Y')
                                ->icon('heroicon-o-calendar-days')
                                ->placeholder('—'),

                            TextEntry::make('creator.name')
                                ->label(__('app.label.created_by'))
                                ->icon('heroicon-o-user')
                                ->placeholder('—'),

                            TextEntry::make('created_at')
                                ->label(__('app.label.created_at'))
                                ->dateTime('d.m.Y H:i')
                                ->icon('heroicon-o-clock'),

                            TextEntry::make('updated_at')
                                ->label(__('app.label.updated_at'))
                                ->dateTime('d.m.Y H:i')
                                ->icon('heroicon-o-pencil'),
                        ]),
                    ]),
            ]);
    }
}
