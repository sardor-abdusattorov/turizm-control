<?php

namespace App\Filament\Resources\ContractTemplates\Schemas;

use App\Filament\Support\DocumentUpload;
use App\Models\ContractTemplate;
use App\Models\OrderType;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

class ContractTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('app.label.basic_information'))
                    ->collapsible()
                    ->schema([
                        TextInput::make('name')
                            ->label(__('app.label.contract_template_name'))
                            ->required()
                            ->maxLength(255)
                            ->placeholder(__('app.helper.contract_template_name_example'))
                            ->columnSpanFull(),

                        Grid::make(3)->schema([
                            Select::make('order_type_id')
                                ->label(__('app.label.order_type_single'))
                                ->options(OrderType::getActive())
                                ->searchable()
                                ->preload()
                                ->placeholder(__('app.label.no_category'))
                                ->columnSpan(2),

                            TextInput::make('sort')
                                ->label(__('app.label.sort'))
                                ->numeric()
                                ->default(0)
                                ->columnSpan(1),
                        ]),

                        Toggle::make('status')
                            ->label(__('app.label.status'))
                            ->default(true)
                            ->inline(false),
                    ]),

                Section::make(__('app.label.contract_template_file'))
                    ->collapsible()
                    ->schema([
                        DocumentUpload::make('contract-templates', 'template_file')
                            ->label(__('app.label.template_file'))
                            ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.wordprocessingml.document'])
                            ->required()
                            ->maxSize(10240)
                            ->helperText(__('app.helper.template_file')),

                        Actions::make([
                            Action::make('openTemplateInEditor')
                                ->label(__('app.action.open_template_in_editor'))
                                ->icon('heroicon-o-pencil-square')
                                ->color('primary')
                                ->url(function (?ContractTemplate $record, $livewire) {
                                    if (! $record) {
                                        return null;
                                    }

                                    $mode = $livewire instanceof ViewRecord ? 'view' : 'edit';

                                    return route('contract-templates.editor', [
                                        'template' => $record,
                                        'mode' => $mode,
                                    ]);
                                })
                                ->visible(fn (?ContractTemplate $record) => $record?->templateExists() ?? false),
                        ]),
                    ]),

                Section::make(__('app.label.available_placeholders'))
                    ->collapsible()
                    ->collapsed()
                    ->description(__('app.helper.placeholders_click_to_copy'))
                    ->schema([
                        View::make('filament.resources.contract-templates.partials.placeholders')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
