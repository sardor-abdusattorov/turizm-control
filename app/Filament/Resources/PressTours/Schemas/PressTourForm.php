<?php

namespace App\Filament\Resources\PressTours\Schemas;

use App\Enums\PressTourDirection;
use App\Enums\PressTourState;
use App\Models\Order;
use App\Models\PressTour;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class PressTourForm
{
    protected static function hintUnlessViewing(string $example): Closure
    {
        return fn (string $operation): ?string => $operation === 'view' ? null : $example;
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema

            ->columns(1)
            ->components([
                Tabs::make('press_tour')
                    ->columnSpanFull()
                    ->schema([
                        Tabs\Tab::make(__('app.label.basic_information'))
                            ->icon('heroicon-o-information-circle')
                            ->schema([
                                Select::make('direction')
                                    ->label(__('app.label.press_tour_direction'))
                                    ->options(PressTourDirection::options())
                                    ->default(PressTourDirection::Local->value)
                                    ->selectablePlaceholder(false)
                                    ->required()
                                    ->columnSpanFull(),

                                TextInput::make('name')
                                    ->label(__('app.label.press_tour_name'))
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpanFull(),

                                Grid::make(['default' => 1, 'md' => 2])
                                    ->schema([

                                        TextInput::make('place')
                                            ->label(__('app.label.press_tour_place'))
                                            ->placeholder(self::hintUnlessViewing('Самарканд'))
                                            ->maxLength(255),

                                        Select::make('order_id')
                                            ->label(__('app.label.order_basis'))
                                            ->options(fn (): array => Order::basisOptions())
                                            ->searchable()
                                            ->preload(),
                                    ]),

                                Textarea::make('notes')
                                    ->label(__('app.label.press_tour_notes'))
                                    ->rows(3)
                                    ->columnSpanFull(),

                                Toggle::make('status')
                                    ->label(__('app.label.status'))
                                    ->default(true)
                                    ->inline(false),
                            ]),

                        Tabs\Tab::make(__('app.label.press_tour_schedule'))
                            ->icon('heroicon-o-calendar')
                            ->schema([
                                Grid::make(['default' => 1, 'md' => 2])
                                    ->schema([

                                        TextInput::make('period')
                                            ->label(__('app.label.press_tour_period'))
                                            ->placeholder(self::hintUnlessViewing('11-18 Август'))
                                            ->maxLength(255),

                                        Select::make('starts_month')
                                            ->label(__('app.label.press_tour_month'))
                                            ->options(PressTour::monthOptions())
                                            ->searchable(),

                                        TextInput::make('people_count')
                                            ->label(__('app.label.press_tour_people'))
                                            ->numeric()
                                            ->minValue(0)
                                            ->maxValue(65535),

                                        TextInput::make('people_note')
                                            ->label(__('app.label.press_tour_people_note'))
                                            ->placeholder(self::hintUnlessViewing('6+11'))
                                            ->maxLength(255),
                                    ]),
                            ]),

                        Tabs\Tab::make(__('app.label.press_tour_people_section'))
                            ->icon('heroicon-o-users')
                            ->schema([
                                Grid::make(['default' => 1, 'md' => 2])
                                    ->schema([
                                        TextInput::make('responsible')
                                            ->label(__('app.label.responsible'))
                                            ->maxLength(255),

                                        TextInput::make('curator')
                                            ->label(__('app.label.press_tour_curator'))
                                            ->maxLength(255),
                                    ]),

                                TextInput::make('foreign_partner')
                                    ->label(__('app.label.press_tour_foreign_partner'))
                                    ->maxLength(255)
                                    ->columnSpanFull(),
                            ]),

                        Tabs\Tab::make(__('app.label.press_tour_progress'))
                            ->icon('heroicon-o-check-circle')
                            ->schema([
                                Grid::make(['default' => 1, 'md' => 2])
                                    ->schema([
                                        Select::make('state')
                                            ->label(__('app.label.press_tour_state'))
                                            ->options(PressTourState::options())
                                            ->default(PressTourState::Planned->value)
                                            ->selectablePlaceholder(false)
                                            ->required()
                                            ->live(),

                                        DatePicker::make('held_on')
                                            ->label(__('app.label.press_tour_held_on'))
                                            ->native(false)
                                            ->displayFormat('d.m.Y')
                                            ->visible(fn (Get $get): bool => $get('state') === PressTourState::Held->value)
                                            ->required(fn (Get $get): bool => $get('state') === PressTourState::Held->value),
                                    ]),
                            ]),
                    ]),
            ]);
    }
}
