<?php

namespace App\Filament\Resources\Projects\Schemas;

use App\Enums\ProjectType;
use App\Filament\Support\MediaGalleryUpload;
use App\Models\Currency;
use App\Models\Project;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class ProjectForm
{
    /**
     * The form is shared by both typed resources; the page's resource pins
     * the type (BaseProjectResource::projectType), so visibility of the
     * local-event vs exhibition fields keys off the hosting Livewire page.
     */
    protected static function isInternal($livewire): bool
    {
        return $livewire::getResource()::projectType() === ProjectType::Internal;
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('project')
                    ->columnSpanFull()
                    ->schema([
                        Tabs\Tab::make(__('app.label.basic_information'))
                            ->icon('heroicon-o-information-circle')
                            ->schema([
                                TextInput::make('name')
                                    ->label(__('app.label.project_name'))
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpanFull(),

                                TextInput::make('venue')
                                    ->label(__('app.label.venue'))
                                    ->placeholder('Madrid, Spain')
                                    ->maxLength(255)
                                    ->columnSpanFull(),

                                Grid::make(['default' => 1, 'md' => 2])
                                    ->schema([
                                        DatePicker::make('starts_on')
                                            ->label(__('app.label.starts_on'))
                                            ->native(false)
                                            ->displayFormat('d.m.Y')
                                            ->prefixIcon('heroicon-o-calendar-days')
                                            ->closeOnDateSelection(),

                                        DatePicker::make('ends_on')
                                            ->label(__('app.label.ends_on'))
                                            ->native(false)
                                            ->displayFormat('d.m.Y')
                                            ->prefixIcon('heroicon-o-calendar-days')
                                            ->closeOnDateSelection()
                                            ->afterOrEqual('starts_on'),
                                    ]),

                                // Local events keep only the photo-report link —
                                // all money and participation figures come from
                                // the project's contracts now.
                                Grid::make(['default' => 1, 'md' => 2])
                                    ->visible(self::isInternal(...))
                                    ->schema([
                                        TextInput::make('photo_report_url')
                                            ->label(__('app.label.photo_report_url'))
                                            ->url()
                                            ->maxLength(255)
                                            ->placeholder('https://clck.ru/…'),
                                    ]),

                                Textarea::make('description')
                                    ->label(__('app.label.description'))
                                    ->rows(3)
                                    ->columnSpanFull(),

                                Toggle::make('status')
                                    ->label(__('app.label.status'))
                                    ->default(true),
                            ]),

                        Tabs\Tab::make(__('app.label.project_costs'))
                            ->icon('heroicon-o-banknotes')
                            // Area and stand money belong to exhibitions; a
                            // local event's money lives in estimate/final.
                            ->visible(fn ($livewire): bool => ! self::isInternal($livewire))
                            ->schema([
                                TextInput::make('area_sqm')
                                    ->label(__('app.label.area_sqm'))
                                    ->numeric()
                                    ->step(0.01)
                                    ->minValue(0)
                                    ->suffix('м²')
                                    ->columnSpanFull(),

                                Toggle::make('area_is_free')
                                    ->label(__('app.label.area_is_free'))
                                    ->live(),

                                // One currency drives both cost fields; the stand
                                // gets its own only when it genuinely differs
                                // (e.g. ITB-2025: area in EUR, stand in USD).
                                Select::make('area_currency_id')
                                    ->label(__('app.label.currency_single'))
                                    ->options(fn () => Currency::getActive())
                                    ->live()
                                    ->afterStateUpdated(function (Set $set, Get $get, ?string $state): void {
                                        if (! $get('stand_currency_differs')) {
                                            $set('stand_currency_id', $state);
                                        }
                                    })
                                    ->columnSpanFull(),

                                TextInput::make('area_cost')
                                    ->label(__('app.label.area_cost'))
                                    ->numeric()
                                    ->step(0.01)
                                    ->minValue(0)
                                    ->prefix(fn (Get $get): ?string => Currency::find($get('area_currency_id'))?->short_name)
                                    ->visible(fn (Get $get): bool => ! $get('area_is_free'))
                                    ->columnSpanFull(),

                                TextInput::make('stand_cost')
                                    ->label(__('app.label.stand_cost'))
                                    ->numeric()
                                    ->step(0.01)
                                    ->minValue(0)
                                    ->prefix(fn (Get $get): ?string => Currency::find($get('stand_currency_id') ?? $get('area_currency_id'))?->short_name)
                                    ->columnSpanFull(),

                                Toggle::make('stand_currency_differs')
                                    ->label(__('app.label.stand_currency_differs'))
                                    ->dehydrated(false)
                                    ->live()
                                    ->afterStateHydrated(function (Toggle $component, ?Project $record): void {
                                        $component->state(
                                            $record !== null
                                            && $record->stand_currency_id !== null
                                            && $record->stand_currency_id !== $record->area_currency_id,
                                        );
                                    })
                                    ->afterStateUpdated(function (Set $set, Get $get, bool $state): void {
                                        if (! $state) {
                                            $set('stand_currency_id', $get('area_currency_id'));
                                        }
                                    }),

                                Select::make('stand_currency_id')
                                    ->label(__('app.label.stand_currency'))
                                    ->options(fn () => Currency::getActive())
                                    ->live()
                                    ->visible(fn (Get $get): bool => (bool) $get('stand_currency_differs'))
                                    ->columnSpanFull(),
                            ]),

                        Tabs\Tab::make(__('app.label.gallery'))
                            ->icon('heroicon-o-photo')
                            ->schema([
                                MediaGalleryUpload::make('projects', 'gallery')
                                    ->hiddenLabel(),
                            ]),
                    ]),
            ]);
    }
}
