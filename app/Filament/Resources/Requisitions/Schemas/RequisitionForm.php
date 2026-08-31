<?php

namespace App\Filament\Resources\Requisitions\Schemas;

use App\Enums\ApprovalStatus;
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
                    ->visible(fn (?Requisition $record): bool => filled(static::lastVerdict($record)))
                    ->schema([
                        TextEntry::make('last_verdict')
                            ->label(__('app.approval.field.reason'))
                            ->state(fn (?Requisition $record): ?string => static::lastVerdict($record))
                            ->color('danger')
                            ->columnSpanFull(),
                    ]),

                Section::make(__('app.label.basic_information'))
                    ->icon('heroicon-o-clipboard-document-list')
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
                                Select::make('project_id')
                                    ->label(__('app.label.project_single'))
                                    ->options(fn (): array => Project::groupedOptions())
                                    ->searchable()
                                    ->preload()
                                    ->nullable(),
                            ]),
                    ]),

                Section::make(__('app.approval.section'))
                    ->icon('heroicon-o-user-group')
                    ->description(__('app.approval.section_description'))
                    ->schema([
                        Select::make('approver_ids')
                            ->label(__('app.approval.field.approvers'))
                            ->helperText(__('app.approval.field.approvers_help'))
                            ->multiple()
                            ->options(fn (): array => User::activeOptionsGroupedByDepartment())
                            ->default(fn (): array => Requisition::defaultApproverIds())
                            ->afterStateHydrated(function (Select $component, ?Requisition $record): void {
                                if ($record) {
                                    $component->state($record->activeApprovals()->pluck('user_id')->all());
                                }
                            })
                            ->dehydrated()
                            ->allowHtml()
                            ->searchable()
                            ->preload()
                            ->required()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * The reason the last round came back, shown at the top of the form the
     * author reopens — that is the whole point of them being here.
     */
    protected static function lastVerdict(?Requisition $record): ?string
    {
        return $record?->approvals()
            ->where('status', ApprovalStatus::Rejected)
            ->orderByDesc('acted_at')
            ->value('comment');
    }
}
