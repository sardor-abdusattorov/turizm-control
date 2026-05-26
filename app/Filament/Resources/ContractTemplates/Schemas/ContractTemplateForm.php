<?php

namespace App\Filament\Resources\ContractTemplates\Schemas;

use AbdulmajeedJamaan\FilamentTranslatableTabs\TranslatableTabs;
use App\Models\OrderType;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ContractTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('app.label.basic_information'))
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 2])
                            ->schema([
                                Select::make('order_type_id')
                                    ->label(__('app.label.order_type_single'))
                                    ->options(OrderType::getActive())
                                    ->required()
                                    ->searchable()
                                    ->preload(),

                                TextInput::make('sort')
                                    ->label(__('app.label.sort'))
                                    ->numeric()
                                    ->default(0),
                            ]),

                        TranslatableTabs::make()
                            ->schema([
                                TextInput::make('name')
                                    ->label(__('app.label.contract_template_name'))
                                    ->required()
                                    ->maxLength(255),
                            ]),

                        Toggle::make('status')
                            ->label(__('app.label.status'))
                            ->default(true),
                    ]),

                Section::make(__('app.label.contract_template_fields'))
                    ->description(__('app.helper.contract_template_fields'))
                    ->schema([
                        Repeater::make('fields')
                            ->label(__('app.label.fields'))
                            ->reorderable()
                            ->reorderableWithDragAndDrop()
                            ->addActionLabel(__('app.action.add_field'))
                            ->schema([
                                Grid::make(['default' => 1, 'md' => 3])
                                    ->schema([
                                        TextInput::make('name')
                                            ->label(__('app.label.field_key'))
                                            ->required()
                                            ->regex('/^[a-z][a-z0-9_]*$/i')
                                            ->helperText(__('app.helper.field_key')),

                                        TextInput::make('label')
                                            ->label(__('app.label.field_label'))
                                            ->required(),

                                        Select::make('type')
                                            ->label(__('app.label.field_type'))
                                            ->options([
                                                'text' => __('app.label.field_type_text'),
                                                'textarea' => __('app.label.field_type_textarea'),
                                                'number' => __('app.label.field_type_number'),
                                                'date' => __('app.label.field_type_date'),
                                            ])
                                            ->default('text')
                                            ->required()
                                            ->native(false),
                                    ]),

                                Toggle::make('required')
                                    ->label(__('app.label.field_required'))
                                    ->default(false),
                            ])
                            ->itemLabel(fn (array $state): ?string => $state['label'] ?? $state['name'] ?? null)
                            ->collapsible()
                            ->collapsed(),
                    ]),

                Section::make(__('app.label.contract_template_content'))
                    ->description(__('app.helper.contract_template_content'))
                    ->schema([
                        TranslatableTabs::make()
                            ->schema([
                                Textarea::make('content')
                                    ->label(__('app.label.content'))
                                    ->required()
                                    ->rows(20)
                                    ->extraInputAttributes(['style' => 'font-family: monospace; font-size: 13px;']),
                            ]),
                    ]),
            ]);
    }
}
