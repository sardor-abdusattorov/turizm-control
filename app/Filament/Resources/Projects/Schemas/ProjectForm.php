<?php

namespace App\Filament\Resources\Projects\Schemas;

use App\Enums\ParticipantRole;
use App\Filament\Resources\Sponsors\Schemas\SponsorForm;
use App\Filament\Support\ImageGalleryUpload;
use App\Models\Contact;
use App\Models\Currency;
use App\Models\Order;
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
                                Grid::make(['default' => 1, 'md' => 2])
                                    ->schema([
                                        TextInput::make('name')
                                            ->label(__('app.label.project_name'))
                                            ->required()
                                            ->maxLength(255),

                                        DatePicker::make('starts_on')
                                            ->label(__('app.label.starts_on'))
                                            ->native(false)
                                            ->displayFormat('d.m.Y'),

                                        DatePicker::make('ends_on')
                                            ->label(__('app.label.ends_on'))
                                            ->native(false)
                                            ->displayFormat('d.m.Y')
                                            ->afterOrEqual('starts_on'),

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
                                            ->nullable(),

                                        Toggle::make('status')
                                            ->label(__('app.label.status'))
                                            ->default(true)
                                            ->inline(false),
                                    ]),

                                Textarea::make('description')
                                    ->label(__('app.label.description'))
                                    ->rows(3)
                                    ->columnSpanFull(),
                            ]),

                        Tabs\Tab::make(__('app.label.project_costs'))
                            ->icon('heroicon-o-banknotes')
                            ->schema([
                                Grid::make(['default' => 1, 'md' => 3])
                                    ->schema([
                                        TextInput::make('area_sqm')
                                            ->label(__('app.label.area_sqm'))
                                            ->numeric()
                                            ->step(0.01)
                                            ->minValue(0)
                                            ->suffix('м²'),

                                        TextInput::make('area_cost')
                                            ->label(__('app.label.area_cost'))
                                            ->numeric()
                                            ->step(0.01)
                                            ->minValue(0)
                                            ->prefix(fn (Get $get): ?string => Currency::find($get('area_currency_id'))?->short_name)
                                            ->visible(fn (Get $get): bool => ! $get('area_is_free')),

                                        Select::make('area_currency_id')
                                            ->label(__('app.label.area_currency'))
                                            ->options(fn () => Currency::getActive())
                                            ->live()
                                            ->visible(fn (Get $get): bool => ! $get('area_is_free')),

                                        Toggle::make('area_is_free')
                                            ->label(__('app.label.area_is_free'))
                                            ->live()
                                            ->inline(false),

                                        TextInput::make('stand_cost')
                                            ->label(__('app.label.stand_cost'))
                                            ->numeric()
                                            ->step(0.01)
                                            ->minValue(0)
                                            ->prefix(fn (Get $get): ?string => Currency::find($get('stand_currency_id'))?->short_name),

                                        Select::make('stand_currency_id')
                                            ->label(__('app.label.stand_currency'))
                                            ->options(fn () => Currency::getActive())
                                            ->live(),
                                    ]),
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
                                    ->columns(['default' => 1, 'md' => 2])
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
                                            ->maxLength(255)
                                            ->columnSpanFull(),

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
