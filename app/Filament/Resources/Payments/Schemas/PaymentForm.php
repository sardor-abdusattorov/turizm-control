<?php

namespace App\Filament\Resources\Payments\Schemas;

use App\Enums\PaymentStatus;
use App\Filament\Support\ImageUpload;
use App\Models\Contract;
use App\Models\Payment;
use App\Rules\PaymentWithinRemaining;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class PaymentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make()
                    ->columns(2)
                    ->schema([
                        Select::make('contract_id')
                            ->label(__('app.label.contract'))
                            ->options(fn () => self::approvedContractOptions())
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
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
                            ->columnSpanFull(),

                        TextInput::make('percent')
                            ->label(__('app.label.percent'))
                            ->numeric()
                            ->required()
                            ->minValue(0.01)
                            ->maxValue(100)
                            ->step('0.01')
                            ->suffix('%')
                            ->helperText(fn (Get $get): ?string => self::remainingHelper($get('contract_id')))
                            ->rule(static fn (Get $get) => new PaymentWithinRemaining(Contract::find($get('contract_id')))),

                        DatePicker::make('paid_at')
                            ->label(__('app.label.paid_at'))
                            ->required()
                            ->native(false)
                            ->maxDate(now()),

                        ImageUpload::make(Payment::SCREENSHOT_DIR, 'screenshot')
                            ->label(__('app.label.screenshot'))
                            ->required()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * Build the contract dropdown — approved contracts that still have room
     * for another instalment.
     *
     * @return array<int, string>
     */
    private static function approvedContractOptions(): array
    {
        return Contract::query()
            ->visibleTo()
            ->where('status', Contract::STATUS_APPROVED)
            ->where('payment_status', '!=', PaymentStatus::FullyPaid->value)
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
