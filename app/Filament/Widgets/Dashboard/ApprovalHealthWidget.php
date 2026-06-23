<?php

namespace App\Filament\Widgets\Dashboard;

use App\Models\ContractApprover;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class ApprovalHealthWidget extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    protected static ?int $sort = 8;

    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole(['director', 'super_admin']) ?? false;
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
                ->icon('heroicon-o-inbox-stack'),

            Stat::make(__('app.stats.on_time_rate'), $m['on_time_rate'] === null ? '—' : $m['on_time_rate'].'%')
                ->description(__('app.stats.on_time_of', ['count' => $m['decided']]))
                ->descriptionIcon('heroicon-m-clock')
                ->color($m['on_time_rate'] === null ? 'gray' : ($m['on_time_rate'] >= 80 ? 'success' : 'warning'))
                ->icon('heroicon-o-bolt'),

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
     * @return array{pending: int, overdue: int, decided: int, on_time_rate: ?int, bottleneck_name: ?string, bottleneck_count: int}
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
        ];
    }
}
