<?php

namespace App\Filament\Widgets;

use App\Enums\PaymentStatus;
use App\Filament\Resources\Contracts\ContractResource;
use App\Filament\Resources\Payments\PaymentResource;
use App\Models\Contract;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PaymentStatsWidget extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        // Money lives where money is decided: accountants record it, the
        // director signs off, super_admin oversees. Everyone else doesn't need
        // it on their dashboard.
        return $user->hasAnyRole(['accountant', 'director', 'super_admin']);
    }

    protected function getStats(): array
    {
        // Convert every contract's amount to UZS using the snapshot rate that
        // lives on the currency record so the totals are comparable across
        // currencies. `value = 1` for UZS itself, so the math is the same.
        $approvedQuery = Contract::query()
            ->visibleTo()
            ->where('status', Contract::STATUS_APPROVED)
            ->with('currency');

        $approvedTotal = 0.0;
        $collectedTotal = 0.0;
        $outstandingTotal = 0.0;
        $fullyPaidCount = 0;

        foreach ($approvedQuery->get() as $contract) {
            $rate = (float) ($contract->currency?->value ?? 1);
            $valueUzs = (float) $contract->amount * $rate;
            $paidPercent = (float) $contract->paid_percent;

            $approvedTotal += $valueUzs;
            $collectedTotal += $valueUzs * $paidPercent / 100;
            $outstandingTotal += $valueUzs * (100 - min(100, $paidPercent)) / 100;

            if ($contract->payment_status === PaymentStatus::FullyPaid) {
                $fullyPaidCount++;
            }
        }

        $format = static fn (float $value): string => number_format(round($value), 0, '.', ' ').' UZS';

        $contractsUrl = ContractResource::getUrl('index');
        $paymentsUrl = PaymentResource::getUrl('index');

        return [
            Stat::make(__('app.stats.approved_value'), $format($approvedTotal))
                ->description(__('app.stats.approved_value_description'))
                ->descriptionIcon('heroicon-m-document-check')
                ->color('primary')
                ->url($contractsUrl.'?tableFilters[status][value]='.Contract::STATUS_APPROVED->value),

            Stat::make(__('app.stats.collected'), $format($collectedTotal))
                ->description(__('app.stats.collected_description'))
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success')
                ->url($paymentsUrl),

            Stat::make(__('app.stats.outstanding'), $format($outstandingTotal))
                ->description(__('app.stats.outstanding_description'))
                ->descriptionIcon('heroicon-m-clock')
                ->color($outstandingTotal > 0 ? 'warning' : 'gray')
                ->url($contractsUrl.'?tableFilters[payment_status][value]='.PaymentStatus::PartiallyPaid->value),

            Stat::make(__('app.stats.fully_paid_count'), $fullyPaidCount)
                ->description(__('app.stats.fully_paid_count_description'))
                ->descriptionIcon('heroicon-m-check-badge')
                ->color($fullyPaidCount > 0 ? 'success' : 'gray')
                ->url($contractsUrl.'?tableFilters[payment_status][value]='.PaymentStatus::FullyPaid->value),
        ];
    }
}
