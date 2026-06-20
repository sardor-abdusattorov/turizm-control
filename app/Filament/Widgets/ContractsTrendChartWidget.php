<?php

namespace App\Filament\Widgets;

use App\Models\Contract;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Collection;

class ContractsTrendChartWidget extends ChartWidget
{
    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 20;

    protected ?string $maxHeight = '240px';

    public function getHeading(): ?string
    {
        return __('app.stats.contracts_trend_heading');
    }

    public function getDescription(): ?string
    {
        return __('app.stats.contracts_trend_description');
    }

    public static function canView(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $user->hasAnyRole(['super_admin', 'director', 'accountant'])
            || $user->can('view_all_contracts');
    }

    protected function getData(): array
    {
        $months = $this->buildMonthBuckets(6);
        $windowStart = $months->first()['start'];

        $contracts = Contract::query()
            ->visibleTo()
            ->where(function ($query) use ($windowStart): void {
                $query->where('created_at', '>=', $windowStart)
                    ->orWhere('signed_at', '>=', $windowStart->toDateString());
            })
            ->get(['created_at', 'signed_at']);

        $createdByBucket = $contracts
            ->filter(fn ($c) => $c->created_at && $c->created_at->greaterThanOrEqualTo($windowStart))
            ->countBy(fn ($c) => $c->created_at->format('Y-m'));

        $signedByBucket = $contracts
            ->filter(fn ($c) => $c->signed_at && $c->signed_at->greaterThanOrEqualTo($windowStart))
            ->countBy(fn ($c) => $c->signed_at->format('Y-m'));

        $createdSeries = $months->map(fn (array $m) => (int) ($createdByBucket[$m['key']] ?? 0))->all();
        $signedSeries = $months->map(fn (array $m) => (int) ($signedByBucket[$m['key']] ?? 0))->all();

        return [
            'datasets' => [
                [
                    'label' => __('app.stats.contracts_created'),
                    'data' => $createdSeries,
                    'borderColor' => 'rgb(59, 130, 246)',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.15)',
                    'tension' => 0.35,
                    'fill' => true,
                ],
                [
                    'label' => __('app.stats.contracts_signed'),
                    'data' => $signedSeries,
                    'borderColor' => 'rgb(16, 185, 129)',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.15)',
                    'tension' => 0.35,
                    'fill' => true,
                ],
            ],
            'labels' => $months->pluck('label')->all(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                    'labels' => ['boxWidth' => 12, 'padding' => 12],
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => ['precision' => 0],
                ],
            ],
        ];
    }

    /**
     * Build the rolling N-month window the chart spans, with the bucket key
     * (YYYY-MM) used to join Eloquent results and a localized label for the
     * x-axis.
     *
     * @return Collection<int, array{key: string, label: string, start: CarbonImmutable}>
     */
    private function buildMonthBuckets(int $count): Collection
    {
        $now = CarbonImmutable::now()->startOfMonth();

        return collect(range($count - 1, 0))->map(function (int $offset) use ($now): array {
            $month = $now->subMonths($offset);

            return [
                'key' => $month->format('Y-m'),
                'label' => $month->translatedFormat('M Y'),
                'start' => $month,
            ];
        });
    }
}
