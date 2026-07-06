<?php

namespace App\Filament\Resources\Projects\Schemas;

use App\Enums\ParticipantRole;
use App\Filament\Resources\Sponsors\Schemas\SponsorForm;
use App\Filament\Support\ImageGalleryUpload;
use App\Models\Contact;
use App\Models\Currency;
use App\Models\Order;
use App\Models\Project;
use App\Models\Sponsor;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class ProjectForm
{
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

                                Select::make('order_id')
                                    ->label(__('app.label.order_single'))
                                    ->relationship(
                                        'order',
                                        'title',
                                        fn (Builder $query): Builder => $query->active(),
                                    )
                                    ->getOptionLabelFromRecordUsing(fn (Order $record): string => trim(
                                        ($record->number ? $record->number.' · ' : '').$record->title,
                                    ))
                                    ->searchable(['number', 'title'])
                                    ->preload()
                                    ->nullable()
                                    ->columnSpanFull(),

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

                        Tabs\Tab::make(__('app.label.participants'))
                            ->icon('heroicon-o-user-group')
                            ->schema([
                                Repeater::make('participants')
                                    ->hiddenLabel()
                                    ->relationship()
                                    ->orderColumn('sort')
                                    ->defaultItems(0)
                                    ->collapsible()
                                    ->collapsed()
                                    ->cloneable()
                                    ->addActionLabel(__('app.action.add_participant'))
                                    ->itemLabel(fn (array $state): ?string => filled($state['name'] ?? null)
                                        ? $state['name'].(filled($state['amount'] ?? null) ? ' · '.number_format((float) $state['amount'], 0, ',', ' ') : '')
                                        : null)
                                    ->schema([
                                        Select::make('role')
                                            ->label(__('app.label.participant_role'))
                                            ->options(ParticipantRole::options())
                                            ->default(ParticipantRole::Participant->value)
                                            ->live()
                                            ->required(),

                                        Select::make('contact_id')
                                            ->label(__('app.label.contact_single'))
                                            ->options(fn () => Contact::getActive())
                                            ->searchable()
                                            ->nullable()
                                            ->live()
                                            ->visible(fn (Get $get): bool => $get('role') !== ParticipantRole::Sponsor->value)
                                            ->afterStateUpdated(function (Set $set, Get $get, ?string $state): void {
                                                if ($state && blank($get('name'))) {
                                                    $set('name', Contact::find($state)?->getTranslation('name', app()->getLocale()));
                                                }
                                            }),

                                        Select::make('sponsor_id')
                                            ->label(__('app.label.sponsor_single'))
                                            ->options(fn () => Sponsor::getActive())
                                            ->searchable()
                                            ->nullable()
                                            ->live()
                                            ->visible(fn (Get $get): bool => $get('role') === ParticipantRole::Sponsor->value)
                                            ->createOptionForm(fn (Schema $schema) => SponsorForm::configure($schema))
                                            ->createOptionUsing(fn (array $data) => Sponsor::create($data)->getKey())
                                            ->afterStateUpdated(function (Set $set, Get $get, ?string $state): void {
                                                if ($state && blank($get('name'))) {
                                                    $set('name', Sponsor::find($state)?->name);
                                                }
                                            }),

                                        TextInput::make('name')
                                            ->label(__('app.label.participant_name'))
                                            ->required()
                                            ->maxLength(255),

                                        TextInput::make('amount')
                                            ->label(__('app.label.participant_amount'))
                                            ->numeric()
                                            ->step(0.01)
                                            ->minValue(0)
                                            ->default(0)
                                            ->required()
                                            ->prefix(fn (Get $get): ?string => Currency::find($get('currency_id'))?->short_name),

                                        Select::make('currency_id')
                                            ->label(__('app.label.currency_single'))
                                            ->options(fn () => Currency::getActive())
                                            ->live()
                                            ->default(fn () => Currency::query()->where('short_name', 'UZS')->value('id')),
                                    ]),
                            ]),

                        Tabs\Tab::make(__('app.label.gallery'))
                            ->icon('heroicon-o-photo')
                            ->schema([
                                ImageGalleryUpload::make('projects', 'gallery')
                                    ->hiddenLabel(),
                            ]),
                    ]),
            ]);
    }
}
