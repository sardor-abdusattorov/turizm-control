<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Filament\Support\DocumentUpload;
use App\Models\OrderType;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrderForm
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
                                TextInput::make('number')
                                    ->label(__('app.label.order_number'))
                                    ->placeholder(__('app.label.order_number_auto'))
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->visibleOn('view'),

                                Select::make('order_type_id')
                                    ->label(__('app.label.order_type_single'))
                                    ->options(OrderType::getActive())
                                    ->required()
                                    ->searchable()
                                    ->preload(),

                                DatePicker::make('issued_at')
                                    ->label(__('app.label.issued_at'))
                                    ->native(false)
                                    ->displayFormat('d.m.Y')
                                    ->default(now())
                                    ->required(),

                                DatePicker::make('deadline_at')
                                    ->label(__('app.label.deadline'))
                                    ->native(false)
                                    ->displayFormat('d.m.Y')
                                    ->helperText(__('app.helper.deadline_optional')),
                            ]),

                        TextInput::make('title')
                            ->label(__('app.label.title'))
                            ->required()
                            ->maxLength(255),

                        Textarea::make('description')
                            ->label(__('app.label.description'))
                            ->rows(4),

                        DocumentUpload::make('orders', 'file_path')
                            ->label(__('app.label.order_file'))
                            ->required(),

                        Toggle::make('status')
                            ->label(__('app.label.status'))
                            ->default(true),
                    ]),
            ]);
    }
}
