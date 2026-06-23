<?php

namespace App\Filament\Widgets;

use App\Enums\ContractStatus;
use App\Filament\Resources\Contracts\ContractResource;
use App\Filament\Support\WidgetPermission;
use App\Models\Contract;
use App\Services\Dashboard\DashboardContext;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ContractStatsWidget extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    protected static ?int $sort = 2;

    public static function canView(): bool
    {
        return WidgetPermission::allows(static::class);
    }

    protected function getStats(): array
    {
        $context = app(DashboardContext::class);
        $counts = $context->managerCounts();
        $trends = $context->managerStatusTrends();

        return [
            Stat::make(__('app.stats.my_in_review'), $counts['in_review'])
                ->description($counts['stalled'] > 0
                    ? __('app.stats.stalled_count', ['count' => $counts['stalled']])
                    : __('app.stats.on_track'))
                ->descriptionIcon($counts['stalled'] > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($counts['stalled'] > 0 ? 'danger' : ($counts['in_review'] > 0 ? 'warning' : 'gray'))
                ->icon('heroicon-o-clock')
                ->chart($trends['in_review'])
                ->url($this->statusUrl(Contract::STATUS_IN_REVIEW)),

            Stat::make(__('app.stats.my_drafts'), $counts['drafts'])
                ->description(__('app.stats.drafts_description'))
                ->descriptionIcon('heroicon-m-pencil-square')
                ->color($counts['drafts'] > 0 ? 'info' : 'gray')
                ->icon('heroicon-o-pencil-square')
                ->chart($trends['drafts'])
                ->url($this->statusUrl(Contract::STATUS_DRAFT)),

            Stat::make(__('app.stats.my_rejected'), $counts['rejected'])
                ->description($counts['rejected'] > 0
                    ? __('app.stats.my_rejected_description')
                    : __('app.stats.no_rejected'))
                ->descriptionIcon('heroicon-m-arrow-uturn-left')
                ->color($counts['rejected'] > 0 ? 'danger' : 'gray')
                ->icon('heroicon-o-x-circle')
                ->chart($trends['rejected'])
                ->url($this->statusUrl(Contract::STATUS_REJECTED)),
        ];
    }

    /**
     * Link to the contract list pre-filtered by status. The manager's list is
     * already scoped to their own contracts and has no tabs, so we only carry
     * the status filter — never an `activeTab`, which would point at a tab that
     * doesn't exist for them.
     */
    private function statusUrl(ContractStatus $status): string
    {
        return ContractResource::getUrl('index', [
            'tableFilters' => ['status' => ['value' => $status->value]],
        ]);
    }
}
