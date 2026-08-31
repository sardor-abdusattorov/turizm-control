<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Enums\OrderScope;
use App\Filament\Support\DocumentUpload;
use App\Models\Order;
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
    public static function configure(Schema $schema, ?OrderScope $scope = null): Schema
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
                                    ->required()
                                    ->maxLength(50)
                                    ->unique('orders', 'number', ignoreRecord: true),

                                DatePicker::make('issued_at')
                                    ->label(__('app.label.issued_at'))
                                    ->native(false)
                                    ->displayFormat('d.m.Y')
                                    ->default(now())
                                    ->required(),
                            ]),

                        Select::make('basis_order_id')
                            ->label(__('app.label.committee_order_basis'))
                            ->helperText(__('app.helper.committee_order_basis'))
                            ->options(fn (): array => Order::committeeBasisOptions())
                            ->getOptionLabelUsing(fn ($value): ?string => Order::find($value)?->label())
                            ->searchable()
                            ->preload()
                            ->visible($scope === OrderScope::PrCenter)
                            ->placeholder(__('app.label.not_set')),

                        TextInput::make('title')
                            ->label(__('app.label.title'))
                            ->required()
                            ->maxLength(255),

                        Textarea::make('description')
                            ->label(__('app.label.description'))
                            ->rows(4),

                        DocumentUpload::make('orders', 'file_path')
                            ->label(__('app.label.order_file'))
                            ->helperText(__('app.helper.order_file_optional')),

                        Toggle::make('status')
                            ->label(__('app.label.status'))
                            ->default(true),
                    ]),
            ]);
    }
}
