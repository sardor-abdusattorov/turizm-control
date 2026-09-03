<?php

namespace App\Filament\Resources\Payments\Schemas;

use App\Enums\PaymentSubject;
use App\Filament\Support\PaymentFilesUpload;
use App\Models\Contract;
use App\Models\Currency;
use App\Models\Payment;
use App\Models\Project;
use App\Rules\PaymentWithinRemaining;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class PaymentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make()
                    ->schema([
                        Radio::make('subject')
                            ->label(__('app.label.payment_subject'))
                            ->options(PaymentSubject::options())
                            ->default(PaymentSubject::Contract->value)
                            ->dehydrated(false)
                            ->inline()
                            ->inlineLabel(false)
                            ->live()
                            ->afterStateHydrated(fn (Set $set, ?Payment $record) => $set(
                                'subject',
                                ($record?->isDirect() ?? false)
                                    ? PaymentSubject::Project->value
                                    : PaymentSubject::Contract->value,
                            ))
                            ->afterStateUpdated(function (Set $set, mixed $state): void {
                                if ($state === PaymentSubject::Project->value) {
                                    $set('contract_id', null);
                                    $set('percent', null);
                                } else {
                                    $set('project_id', null);
                                    $set('amount', null);
                                    $set('currency_id', null);
                                    $set('purpose', null);
                                }
                            })
                            ->disabledOn('view'),

                        Grid::make(['default' => 1, 'md' => 12])
                            ->schema([
                                Select::make('contract_id')
                                    ->label(__('app.label.contract'))
                                    ->options(fn () => self::approvedContractOptions())
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->visible(fn (Get $get): bool => ! self::isDirect($get))
                                    ->required(fn (Get $get): bool => ! self::isDirect($get))
                                    ->disabledOn('view')
                                    ->rule(static fn () => static function (string $attribute, $value, $fail): void {
                                        $contract = Contract::find($value);

                                        if (! $contract) {
                                            $fail(__('app.message.action_not_allowed'));

                                            return;
                                        }

                                        if (! $contract->canAcceptPayment()) {
                                            $fail($contract->isFullyPaid()
                                                ? __('app.message.payment_already_full')
                                                : __('app.message.payment_contract_not_approved'));
                                        }
                                    })
                                    ->columnSpan(['md' => 6]),

                                Select::make('project_id')
                                    ->label(__('app.label.project_single'))
                                    ->options(fn (): array => Project::groupedOptions())
                                    ->searchable()
                                    ->preload()
                                    ->visible(fn (Get $get): bool => self::isDirect($get))
                                    ->required(fn (Get $get): bool => self::isDirect($get))
                                    ->disabledOn('view')
                                    ->columnSpan(['md' => 5]),

                                TextInput::make('percent')
                                    ->label(__('app.label.percent'))
                                    ->numeric()
                                    ->minValue(0.01)
                                    ->maxValue(100)
                                    ->step('0.01')
                                    ->suffix('%')
                                    ->visible(fn (Get $get): bool => ! self::isDirect($get))
                                    ->required(fn (Get $get): bool => ! self::isDirect($get))
                                    ->helperText(fn (Get $get): ?string => self::remainingHelper($get('contract_id')))
                                    ->rule(static fn (Get $get) => new PaymentWithinRemaining(Contract::find($get('contract_id'))))
                                    ->columnSpan(['md' => 3]),

                                Select::make('currency_id')
                                    ->label(__('app.label.currency_single'))
                                    ->options(fn () => Currency::query()->where('status', true)->pluck('short_name', 'id'))
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->visible(fn (Get $get): bool => self::isDirect($get))
                                    ->required(fn (Get $get): bool => self::isDirect($get))
                                    ->columnSpan(['md' => 2]),

                                TextInput::make('amount')
                                    ->label(__('app.label.amount'))
                                    ->numeric()
                                    ->step(0.01)
                                    ->minValue(0.01)
                                    ->prefix(fn (Get $get): ?string => Currency::find($get('currency_id'))?->short_name)
                                    ->visible(fn (Get $get): bool => self::isDirect($get))
                                    ->required(fn (Get $get): bool => self::isDirect($get))
                                    ->columnSpan(['md' => 3]),

                                DatePicker::make('paid_at')
                                    ->label(__('app.label.paid_at'))
                                    ->required()
                                    ->native(false)
                                    ->maxDate(now())
                                    ->columnSpan(fn (Get $get): array => ['md' => self::isDirect($get) ? 2 : 3]),
                            ]),

                        Textarea::make('purpose')
                            ->label(__('app.label.payment_purpose'))
                            ->rows(2)
                            ->maxLength(255)
                            ->visible(fn (Get $get): bool => self::isDirect($get)),

                        PaymentFilesUpload::make(),
                    ]),
            ]);
    }

    private static function isDirect(Get $get): bool
    {
        return $get('subject') === PaymentSubject::Project->value;
    }

    /** @return array<int, string> */
    private static function approvedContractOptions(): array
    {
        return Contract::query()
            ->visibleTo()
            ->acceptingPayments()
            ->orderByDesc('id')
            ->get()
            ->mapWithKeys(fn (Contract $contract) => [
                $contract->id => trim(($contract->number ?? '').' · '.($contract->title ?? '')),
            ])
            ->all();
    }

    private static function remainingHelper(mixed $contractId): ?string
    {
        if (! $contractId) {
            return null;
        }

        $contract = Contract::find($contractId);

        if (! $contract) {
            return null;
        }

        return __('app.label.remaining_to_pay', [
            'percent' => format_percent($contract->remainingPercent()),
        ]);
    }
}
