<?php

namespace App\Filament\Resources\PressTours\Schemas;

use App\Enums\PressTourDirection;
use App\Models\Order;
use App\Models\PressTour;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PressTourForm
{
    /**
     * An example value while filling the form in, nothing while reading it —
     * on the read-only view page a greyed-out sample sits in an empty box
     * exactly where a real value would and reads as data.
     */
    protected static function hintUnlessViewing(string $example): Closure
    {
        return fn (string $operation): ?string => $operation === 'view' ? null : $example;
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('app.label.basic_information'))
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

                        Grid::make(2)->schema([
                            // Domestic tours name a region here, foreign ones
                            // a country — one field covers both.
                            TextInput::make('place')
                                ->label(__('app.label.press_tour_place'))
                                ->placeholder(self::hintUnlessViewing('Самарканд'))
                                ->maxLength(255),

                            // The buyruq the tour rests on — the domestic
                            // programme all sits under приказ № 49-АФ.
                            Select::make('order_id')
                                ->label(__('app.label.order_basis'))
                                ->options(fn (): array => Order::basisOptions())
                                ->searchable()
                                ->preload(),
                        ]),
                    ])
                    ->columns(1),

                Section::make(__('app.label.press_tour_schedule'))
                    ->schema([
                        Grid::make(2)->schema([
                            // The registry writes «11-18 Август» or
                            // «сентябрь - декабрь», so the wording is kept
                            // verbatim and the month is picked separately to
                            // give the list something to sort by.
                            TextInput::make('period')
                                ->label(__('app.label.press_tour_period'))
                                ->placeholder(self::hintUnlessViewing('11-18 Август'))
                                ->maxLength(255),

                            Select::make('starts_month')
                                ->label(__('app.label.press_tour_month'))
                                ->options(PressTour::monthOptions())
                                ->searchable(),
                        ]),

                        Grid::make(2)->schema([
                            TextInput::make('people_count')
                                ->label(__('app.label.press_tour_people'))
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(65535),

                            // «6+11» means two groups travelling together —
                            // a plain number cannot carry that.
                            TextInput::make('people_note')
                                ->label(__('app.label.press_tour_people_note'))
                                ->placeholder(self::hintUnlessViewing('6+11'))
                                ->maxLength(255),
                        ]),
                    ])
                    ->columns(1),

                Section::make(__('app.label.press_tour_people_section'))
                    ->schema([
                        Grid::make(2)->schema([
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
                    ])
                    ->columns(1),

                Section::make(__('app.label.general_information'))
                    ->schema([
                        Textarea::make('notes')
                            ->label(__('app.label.press_tour_notes'))
                            ->rows(3)
                            ->columnSpanFull(),

                        Toggle::make('status')
                            ->label(__('app.label.status'))
                            ->default(true)
                            ->inline(false),
                    ])
                    ->columns(1),
            ]);
    }
}
