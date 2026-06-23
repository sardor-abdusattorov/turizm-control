<?php

namespace App\Filament\Widgets;

use App\Enums\PaymentStatus;
use App\Filament\Resources\Contracts\ContractResource;
use App\Filament\Resources\Payments\PaymentResource;
use App\Models\Contract;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class PaymentStatsWidget extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    protected static ?int $sort = 3;

    public const CACHE_VERSION_KEY = 'dashboard.financial_version';

    public static function bustCache(): void
    {
        Cache::forever(self::CACHE_VERSION_KEY, ((int) Cache::get(self::CACHE_VERSION_KEY, 1)) + 1);
    }

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
        $totals = $this->financialTotals();
        $contractsUrl = ContractResource::getUrl('index');
        $paymentsUrl = PaymentResource::getUrl('index');

        return [
            Stat::make(__('app.stats.approved_value'), self::formatUzs($totals['approved']))
                ->description(__('app.stats.approved_value_description'))
                ->descriptionIcon('heroicon-m-document-check')
                ->color('gray')
                ->icon('heroicon-o-document-check')
                ->url($contractsUrl.'?tableFilters[status][value]='.Contract::STATUS_APPROVED->value),

            Stat::make(__('app.stats.collected'), self::formatUzs($totals['collected']))
                ->description(__('app.stats.collected_description'))
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success')
                ->icon('heroicon-o-banknotes')
                ->url($paymentsUrl),

            Stat::make(__('app.stats.outstanding'), self::formatUzs($totals['outstanding']))
                ->description(__('app.stats.outstanding_description'))
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($totals['outstanding'] > 0 ? 'warning' : 'gray')
                ->icon('heroicon-o-clock')
                ->url($contractsUrl.'?tableFilters[payment_status][value]='.PaymentStatus::PartiallyPaid->value),
        ];
    }

    /**
     * Approved value / collected / outstanding across every visible approved
     * contract, in UZS. Cached for 5 minutes — it sums over all approved
     * contracts and only changes when a payment is booked or a contract is
     * signed, neither of which is second-by-second.
     *
     * @return array{approved: float, collected: float, outstanding: float}
     */
    private function financialTotals(): array
    {
        $userId = (int) (auth()->id() ?? 0);
        $version = Cache::get(self::CACHE_VERSION_KEY, 1);

        return Cache::remember("dashboard.financial_totals.{$userId}.v{$version}", 300, function (): array {
            $approved = 0.0;
            $collected = 0.0;
            $outstanding = 0.0;

            $contracts = Contract::query()
                ->visibleTo()
                ->where('status', Contract::STATUS_APPROVED)
                ->with('currency')
                ->get();

            foreach ($contracts as $contract) {
                $rate = (float) ($contract->currency?->value ?? 1);
                $valueUzs = (float) $contract->amount * $rate;
                $paidPercent = (float) $contract->paid_percent;

                $approved += $valueUzs;
                $collected += $valueUzs * $paidPercent / 100;
                $outstanding += $valueUzs * (100 - min(100, $paidPercent)) / 100;
            }

            return ['approved' => $approved, 'collected' => $collected, 'outstanding' => $outstanding];
        });
    }

    private static function formatUzs(float $value): string
    {
        if ($value >= 1_000_000_000) {
            return number_format($value / 1_000_000_000, 2, '.', ' ').__('app.unit.billion_short').' UZS';
        }

        if ($value >= 1_000_000) {
            return number_format($value / 1_000_000, 1, '.', ' ').__('app.unit.million_short').' UZS';
        }

        if ($value >= 1_000) {
            return number_format($value / 1_000, 1, '.', ' ').__('app.unit.thousand_short').' UZS';
        }

        return number_format(round($value), 0, '.', ' ').' UZS';
    }
}
