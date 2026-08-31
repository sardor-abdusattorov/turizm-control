<?php

namespace App\Filament\Resources\Requisitions\Schemas;

use App\Enums\RequisitionStatus;
use App\Models\Requisition;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RequisitionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make()
                    ->hiddenLabel()
                    ->visible(fn (Requisition $record): bool => filled($record->review_comment))
                    ->schema([
                        TextEntry::make('review_comment')
                            ->label(__('app.label.review_comment'))
                            ->icon(fn (Requisition $record): string => $record->status->icon())
                            ->color(fn (Requisition $record): string => $record->status->color())
                            ->columnSpanFull(),
                    ]),

                Section::make(__('app.label.basic_information'))
                    ->icon('heroicon-o-clipboard-document-list')
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 2])
                            ->schema([
                                TextEntry::make('number')
                                    ->label(__('app.label.requisition_number'))
                                    ->weight('bold')
                                    ->copyable(),

                                TextEntry::make('status')
                                    ->label(__('app.label.status'))
                                    ->badge()
                                    ->color(fn (RequisitionStatus $state): string => $state->color())
                                    ->icon(fn (RequisitionStatus $state): string => $state->icon())
                                    ->formatStateUsing(fn (RequisitionStatus $state): string => $state->label()),
                            ]),

                        TextEntry::make('title')
                            ->label(__('app.label.requisition_title'))
                            ->columnSpanFull(),

                        TextEntry::make('description')
                            ->label(__('app.label.description'))
                            ->columnSpanFull(),

                        TextEntry::make('project.name')
                            ->label(__('app.label.project_single'))
                            ->icon('heroicon-o-presentation-chart-bar')
                            ->placeholder(__('app.label.not_set')),
                    ]),

                Section::make(__('app.label.review'))
                    ->icon('heroicon-o-clipboard-document-check')
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 2])
                            ->schema([
                                TextEntry::make('author.name')
                                    ->label(__('app.label.author'))
                                    ->icon('heroicon-o-user')
                                    ->placeholder(__('app.label.not_set')),

                                TextEntry::make('reviewer.name')
                                    ->label(__('app.label.requisition_reviewer'))
                                    ->icon('heroicon-o-user-circle')
                                    ->placeholder(__('app.label.not_set')),

                                TextEntry::make('submitted_at')
                                    ->label(__('app.label.submitted'))
                                    ->dateTime('d.m.Y H:i')
                                    ->placeholder(__('app.label.not_set')),

                                TextEntry::make('due_at')
                                    ->label(__('app.label.due'))
                                    ->dateTime('d.m.Y H:i')
                                    ->badge()
                                    ->color(fn (Requisition $record): string => $record->isOverdue() ? 'danger' : 'gray')
                                    ->placeholder(__('app.label.not_set')),

                                TextEntry::make('reviewed_at')
                                    ->label(__('app.label.reviewed_at'))
                                    ->dateTime('d.m.Y H:i')
                                    ->placeholder(__('app.label.not_set')),

                                TextEntry::make('created_at')
                                    ->label(__('app.label.created_at'))
                                    ->dateTime('d.m.Y H:i'),
                            ]),
                    ]),
            ]);
    }
}
