<?php

namespace App\Filament\Widgets\Dashboard;

use App\Filament\Support\WidgetPermission;
use App\Models\ContractApprover;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

class ApprovalHealthWidget extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    protected static ?int $sort = 9;

    public static function canView(): bool
    {
        return WidgetPermission::allows(static::class);
    }

    public function getHeading(): ?string
    {
        return __('app.dashboard.approval_health_heading');
    }

    protected function getStats(): array
    {
        $m = Cache::remember('dashboard.approval_health', 300, fn (): array => $this->metrics());

        return [
            Stat::make(__('app.stats.pending_decisions'), $m['pending'])
                ->description($m['overdue'] > 0
                    ? __('app.stats.overdue_count', ['count' => $m['overdue']])
                    : __('app.stats.on_track'))
                ->descriptionIcon($m['overdue'] > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($m['overdue'] > 0 ? 'danger' : 'gray')
                ->chart($m['backlog_trend'])
                ->icon('heroicon-o-inbox-stack'),

            Stat::make(__('app.stats.on_time_rate'), $m['on_time_rate'] === null ? '—' : $m['on_time_rate'].'%')
                ->description(__('app.stats.on_time_of', ['count' => $m['decided']]))
                ->descriptionIcon('heroicon-m-clock')
                ->color($m['on_time_rate'] === null ? 'gray' : ($m['on_time_rate'] >= 80 ? 'success' : 'warning'))
                ->chart($m['on_time_trend'])
                ->icon('heroicon-o-bolt'),

            // The bottleneck is a person, not a series, so it carries no
            // sparkline — a line only belongs on the two genuine trend KPIs.
            Stat::make(__('app.stats.bottleneck'), $m['bottleneck_name'] ?? '—')
                ->description($m['bottleneck_name']
                    ? __('app.stats.bottleneck_desc', ['count' => $m['bottleneck_count']])
                    : __('app.stats.queue_clear'))
                ->descriptionIcon('heroicon-m-user')
                ->color($m['bottleneck_name'] ? 'warning' : 'gray')
                ->icon('heroicon-o-user-group'),
        ];
    }

    /**
     * @return array{pending: int, overdue: int, decided: int, on_time_rate: ?int, bottleneck_name: ?string, bottleneck_count: int, backlog_trend: list<int>, on_time_trend: list<int>}
     */
    private function metrics(): array
    {
        $pending = ContractApprover::query()
            ->where('status', ContractApprover::STATUS_PENDING)
            ->count();

        $overdue = ContractApprover::query()
            ->where('status', ContractApprover::STATUS_PENDING)
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->count();

        $decidedQuery = ContractApprover::query()
            ->whereIn('status', [ContractApprover::STATUS_APPROVED, ContractApprover::STATUS_REJECTED])
            ->whereNotNull('due_at')
            ->whereNotNull('acted_at');

        $decided = (clone $decidedQuery)->count();
        $onTime = (clone $decidedQuery)->whereColumn('acted_at', '<=', 'due_at')->count();
        $onTimeRate = $decided > 0 ? (int) round($onTime / $decided * 100) : null;

        $bottleneck = ContractApprover::query()
            ->where('status', ContractApprover::STATUS_PENDING)
            ->selectRaw('user_id, COUNT(*) as aggregate')
            ->groupBy('user_id')
            ->orderByDesc('aggregate')
            ->with('user')
            ->first();

        return [
            'pending' => $pending,
            'overdue' => $overdue,
            'decided' => $decided,
            'on_time_rate' => $onTimeRate,
            'bottleneck_name' => $bottleneck?->user?->name,
            'bottleneck_count' => (int) ($bottleneck?->aggregate ?? 0),
            'backlog_trend' => $this->backlogTrend(),
            'on_time_trend' => $this->onTimeTrend(),
        ];
    }

    /**
     * Weekly snapshots of the open approval backlog over the last eight weeks:
     * rows that already existed and had not yet been decided at each week's end.
     *
     * @return list<int>
     */
    private function backlogTrend(int $weeks = 8): array
    {
        $points = [];

        for ($i = $weeks - 1; $i >= 0; $i--) {
            $at = now()->subWeeks($i)->endOfWeek();

            $points[] = ContractApprover::query()
                ->where('created_at', '<=', $at)
                ->where(fn (Builder $query) => $query
                    ->whereNull('acted_at')
                    ->orWhere('acted_at', '>', $at))
                ->count();
        }

        return $points;
    }

    /**
     * Weekly on-time rate (percent) over the last eight weeks. Weeks in which
     * nothing was decided inherit the previous value so the line stays
     * continuous instead of dropping to a misleading zero.
     *
     * @return list<int>
     */
    private function onTimeTrend(int $weeks = 8): array
    {
        $points = [];
        $previous = 0;

        for ($i = $weeks - 1; $i >= 0; $i--) {
            $base = ContractApprover::query()
                ->whereIn('status', [ContractApprover::STATUS_APPROVED, ContractApprover::STATUS_REJECTED])
                ->whereNotNull('due_at')
                ->whereNotNull('acted_at')
                ->whereBetween('acted_at', [
                    now()->subWeeks($i)->startOfWeek(),
                    now()->subWeeks($i)->endOfWeek(),
                ]);

            $total = (clone $base)->count();

            if ($total === 0) {
                $points[] = $previous;

                continue;
            }

            $onTime = (clone $base)->whereColumn('acted_at', '<=', 'due_at')->count();
            $previous = (int) round($onTime / $total * 100);
            $points[] = $previous;
        }

        return $points;
    }
}
