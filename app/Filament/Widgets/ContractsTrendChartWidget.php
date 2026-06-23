<?php

namespace App\Filament\Widgets;

use App\Models\Contract;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

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

        return $user->hasAnyRole(['super_admin', 'director', 'accountant', 'legal_officer'])
            || $user->can('view_all_contracts');
    }

    protected function getData(): array
    {
        $months = $this->buildMonthBuckets(6);
        $windowStart = $months->first()['start'];
        $userId = (int) (auth()->id() ?? 0);

        [$createdByBucket, $signedByBucket] = Cache::remember(
            "dashboard.trend.{$userId}.{$windowStart->format('Y-m')}",
            now()->addMinutes(5),
            fn (): array => [
                $this->countByMonth('created_at', $windowStart),
                $this->countByMonth('signed_at', $windowStart),
            ],
        );

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

    /**
     * Count visible contracts per YYYY-MM bucket by the given date column in a
     * single grouped query. The month expression is driver-aware so it works
     * on MySQL (production) and SQLite (tests) alike.
     *
     * @return array<string, int>
     */
    private function countByMonth(string $column, CarbonImmutable $windowStart): array
    {
        $monthExpr = DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', {$column})"
            : "DATE_FORMAT({$column}, '%Y-%m')";

        $start = $column === 'signed_at' ? $windowStart->toDateString() : $windowStart;

        return Contract::query()
            ->visibleTo()
            ->whereNotNull($column)
            ->where($column, '>=', $start)
            ->groupByRaw($monthExpr)
            ->selectRaw("{$monthExpr} as bucket, COUNT(*) as aggregate")
            ->pluck('aggregate', 'bucket')
            ->map(fn ($value): int => (int) $value)
            ->all();
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
