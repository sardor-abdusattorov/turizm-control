<?php

namespace App\Filament\Widgets;

use App\Models\Contract;
use App\Models\ContractApprover;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ContractStatsWidget extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $userId = auth()->id();

        $awaiting = Contract::query()->awaitingApprovalBy()->count();

        $overdue = ContractApprover::query()
            ->where('user_id', $userId)
            ->where('status', ContractApprover::STATUS_PENDING)
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->count();

        $inReview = Contract::query()
            ->where('responsible_id', $userId)
            ->where('status', Contract::STATUS_IN_REVIEW)
            ->count();

        $approved = Contract::query()
            ->where('responsible_id', $userId)
            ->where('status', Contract::STATUS_APPROVED)
            ->count();

        $drafts = Contract::query()
            ->where('responsible_id', $userId)
            ->where('status', Contract::STATUS_DRAFT)
            ->count();

        return [
            Stat::make(__('app.tab.awaiting_me'), $awaiting)
                ->description($overdue > 0
                    ? __('app.stats.overdue_count', ['count' => $overdue])
                    : __('app.stats.on_track'))
                ->descriptionIcon($overdue > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($overdue > 0 ? 'danger' : ($awaiting > 0 ? 'warning' : 'gray')),

            Stat::make(__('app.stats.my_in_review'), $inReview)
                ->descriptionIcon('heroicon-m-clock')
                ->color($inReview > 0 ? 'warning' : 'gray'),

            Stat::make(__('app.stats.my_approved'), $approved)
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('success'),

            Stat::make(__('app.stats.my_drafts'), $drafts)
                ->descriptionIcon('heroicon-m-pencil-square')
                ->color('gray'),
        ];
    }
}
