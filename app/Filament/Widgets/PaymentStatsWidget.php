<?php

namespace App\Filament\Widgets;

use App\Enums\PaymentStatus;
use App\Filament\Resources\Contracts\ContractResource;
use App\Filament\Resources\Payments\PaymentResource;
use App\Models\Contract;
use App\Models\Payment;
use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PaymentStatsWidget extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    protected static ?int $sort = 2;

    public static function canView(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $user->hasAnyRole(['accountant', 'director', 'super_admin']);
    }

    protected function getStats(): array
    {
        // Convert every contract's amount to UZS using the snapshot rate that
        // lives on the currency record so the totals are comparable across
        // currencies (UZS rate is 1, so the math is a no-op for UZS rows).
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

        $sparkline = $this->dailyPaymentsSparkline(14);
        $contractsUrl = ContractResource::getUrl('index');
        $paymentsUrl = PaymentResource::getUrl('index');

        return [
            Stat::make(__('app.stats.approved_value'), self::formatUzs($approvedTotal))
                ->description(__('app.stats.approved_value_description'))
                ->descriptionIcon('heroicon-m-document-check')
                ->color('primary')
                ->icon('heroicon-o-document-check')
                ->chart($sparkline)
                ->url($contractsUrl.'?tableFilters[status][value]='.Contract::STATUS_APPROVED->value),

            Stat::make(__('app.stats.collected'), self::formatUzs($collectedTotal))
                ->description(__('app.stats.collected_description'))
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success')
                ->icon('heroicon-o-banknotes')
                ->chart($sparkline)
                ->url($paymentsUrl),

            Stat::make(__('app.stats.outstanding'), self::formatUzs($outstandingTotal))
                ->description(__('app.stats.outstanding_description'))
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($outstandingTotal > 0 ? 'warning' : 'success')
                ->icon('heroicon-o-clock')
                ->chart($sparkline)
                ->url($contractsUrl.'?tableFilters[payment_status][value]='.PaymentStatus::PartiallyPaid->value),

            Stat::make(__('app.stats.fully_paid_count'), $fullyPaidCount)
                ->description(__('app.stats.fully_paid_count_description'))
                ->descriptionIcon('heroicon-m-check-badge')
                ->color($fullyPaidCount > 0 ? 'success' : 'gray')
                ->icon('heroicon-o-check-badge')
                ->chart($sparkline)
                ->url($contractsUrl.'?tableFilters[payment_status][value]='.PaymentStatus::FullyPaid->value),
        ];
    }

    /**
     * 14-day sparkline of the daily number of payments — gives the money
     * cards a sense of momentum next to the static totals.
     *
     * @return list<int>
     */
    private function dailyPaymentsSparkline(int $days): array
    {
        $start = CarbonImmutable::now()->startOfDay()->subDays($days - 1);

        $byDay = Payment::query()
            ->where('paid_at', '>=', $start->toDateString())
            ->get(['paid_at'])
            ->countBy(fn ($p) => $p->paid_at->format('Y-m-d'));

        $series = [];
        for ($i = 0; $i < $days; $i++) {
            $key = $start->addDays($i)->format('Y-m-d');
            $series[] = (int) ($byDay[$key] ?? 0);
        }

        return $series;
    }

    /** Render a UZS amount short (123 456 789 → "123.5M UZS") so it fits the card. */
    private static function formatUzs(float $value): string
    {
        if ($value >= 1_000_000_000) {
            return number_format($value / 1_000_000_000, 2, '.', ' ').' млрд UZS';
        }

        if ($value >= 1_000_000) {
            return number_format($value / 1_000_000, 1, '.', ' ').' млн UZS';
        }

        if ($value >= 1_000) {
            return number_format($value / 1_000, 1, '.', ' ').' тыс UZS';
        }

        return number_format(round($value), 0, '.', ' ').' UZS';
    }
}
