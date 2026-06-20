<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Contracts\ContractResource;
use App\Models\Contract;
use App\Models\ContractApprover;
use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ContractStatsWidget extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $user->hasAnyRole(['super_admin', 'manager', 'director', 'legal_officer'])
            || $user->hasAnyRole(Contract::OVERSIGHT_ROLES);
    }

    protected function getStats(): array
    {
        $user = auth()->user();
        $listUrl = ContractResource::getUrl('index');

        // Super_admin and director care about company-wide totals; the manager
        // (and everyone else who owns contracts) cares about their own queue.
        $isOversight = $user?->hasAnyRole(Contract::OVERSIGHT_ROLES);
        $scopedTo = $isOversight ? null : $user?->id;

        $awaiting = Contract::query()->awaitingApprovalBy()->count();
        $overdue = ContractApprover::query()
            ->where('user_id', $user?->id)
            ->where('status', ContractApprover::STATUS_PENDING)
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->count();

        $inReviewQuery = Contract::query()->where('status', Contract::STATUS_IN_REVIEW);
        $approvedQuery = Contract::query()->where('status', Contract::STATUS_APPROVED);
        $draftsQuery = Contract::query()->where('status', Contract::STATUS_DRAFT);

        if ($scopedTo) {
            $inReviewQuery->where('responsible_id', $scopedTo);
            $approvedQuery->where('responsible_id', $scopedTo);
            $draftsQuery->where('responsible_id', $scopedTo);
        }

        $inReview = $inReviewQuery->count();
        $approved = $approvedQuery->count();
        $drafts = $draftsQuery->count();

        // 14-day sparkline of "contracts created per day" for the current scope.
        $sparkline = $this->dailyCreatedSparkline($scopedTo, 14);

        return [
            Stat::make(__('app.tab.awaiting_me'), $awaiting)
                ->description($overdue > 0
                    ? __('app.stats.overdue_count', ['count' => $overdue])
                    : __('app.stats.on_track'))
                ->descriptionIcon($overdue > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($overdue > 0 ? 'danger' : ($awaiting > 0 ? 'warning' : 'success'))
                ->icon('heroicon-o-inbox-arrow-down')
                ->chart($sparkline)
                ->url($listUrl.'?activeTab=awaiting_me'),

            Stat::make(
                $isOversight ? __('app.stats.in_review_total') : __('app.stats.my_in_review'),
                $inReview,
            )
                ->description(__('app.stats.in_review_description'))
                ->descriptionIcon('heroicon-m-clock')
                ->color($inReview > 0 ? 'warning' : 'gray')
                ->icon('heroicon-o-clock')
                ->chart($sparkline)
                ->url($listUrl.($isOversight ? '' : '?activeTab=my_contracts').'&tableFilters[status][value]='.Contract::STATUS_IN_REVIEW->value),

            Stat::make(
                $isOversight ? __('app.stats.approved_total') : __('app.stats.my_approved'),
                $approved,
            )
                ->description(__('app.stats.approved_description'))
                ->descriptionIcon('heroicon-m-check-badge')
                ->color($approved > 0 ? 'success' : 'gray')
                ->icon('heroicon-o-check-badge')
                ->chart($sparkline)
                ->url($listUrl.($isOversight ? '' : '?activeTab=my_contracts').'&tableFilters[status][value]='.Contract::STATUS_APPROVED->value),

            Stat::make(
                $isOversight ? __('app.stats.drafts_total') : __('app.stats.my_drafts'),
                $drafts,
            )
                ->description(__('app.stats.drafts_description'))
                ->descriptionIcon('heroicon-m-pencil-square')
                ->color($drafts > 0 ? 'info' : 'gray')
                ->icon('heroicon-o-pencil-square')
                ->chart($sparkline)
                ->url($listUrl.($isOversight ? '' : '?activeTab=my_contracts').'&tableFilters[status][value]='.Contract::STATUS_DRAFT->value),
        ];
    }

    /**
     * Daily count of contracts created over the trailing window, optionally
     * scoped to one responsible user. Used as the sparkline behind each stat.
     *
     * @return list<int>
     */
    private function dailyCreatedSparkline(?int $userId, int $days): array
    {
        $start = CarbonImmutable::now()->startOfDay()->subDays($days - 1);

        $query = Contract::query()
            ->where('created_at', '>=', $start)
            ->get(['created_at', 'responsible_id']);

        if ($userId) {
            $query = $query->where('responsible_id', $userId);
        }

        $byDay = $query->countBy(fn ($c) => $c->created_at->format('Y-m-d'));

        $series = [];
        for ($i = 0; $i < $days; $i++) {
            $key = $start->addDays($i)->format('Y-m-d');
            $series[] = (int) ($byDay[$key] ?? 0);
        }

        return $series;
    }
}
