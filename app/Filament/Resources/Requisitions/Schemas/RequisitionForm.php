<?php

namespace App\Filament\Resources\Requisitions\Schemas;

use App\Models\Project;
use App\Models\Requisition;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RequisitionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make()
                    ->hiddenLabel()
                    ->visible(fn (?Requisition $record): bool => filled($record?->review_comment))
                    ->schema([
                        TextEntry::make('review_comment')
                            ->label(__('app.label.review_comment'))
                            ->color(fn (?Requisition $record): string => $record?->status?->color() ?? 'gray')
                            ->columnSpanFull(),
                    ]),

                Section::make(__('app.label.basic_information'))
                    ->schema([
                        TextInput::make('title')
                            ->label(__('app.label.requisition_title'))
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Textarea::make('description')
                            ->label(__('app.label.description'))
                            ->helperText(__('app.helper.requisition_description'))
                            ->required()
                            ->rows(5)
                            ->autosize()
                            ->columnSpanFull(),

                        Grid::make(['default' => 1, 'md' => 2])
                            ->schema([
                                Select::make('reviewer_id')
                                    ->label(__('app.label.requisition_reviewer'))
                                    ->helperText(__('app.helper.requisition_reviewer'))
                                    ->options(fn (): array => User::activeOptionsGroupedByDepartment())
                                    ->default(fn (): ?int => Requisition::defaultReviewerId())
                                    ->allowHtml()
                                    ->searchable()
                                    ->preload()
                                    ->required(),

                                Select::make('project_id')
                                    ->label(__('app.label.project_single'))
                                    ->options(fn (): array => Project::groupedOptions())
                                    ->searchable()
                                    ->preload()
                                    ->nullable(),
                            ]),
                    ]),
            ]);
    }
}
