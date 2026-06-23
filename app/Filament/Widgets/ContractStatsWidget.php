<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Contracts\ContractResource;
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
        return auth()->user()?->hasRole('manager') ?? false;
    }

    protected function getStats(): array
    {
        $context = app(DashboardContext::class);
        $counts = $context->managerCounts();
        $listUrl = ContractResource::getUrl('index');

        return [
            Stat::make(__('app.stats.my_in_review'), $counts['in_review'])
                ->description($counts['stalled'] > 0
                    ? __('app.stats.stalled_count', ['count' => $counts['stalled']])
                    : __('app.stats.on_track'))
                ->descriptionIcon($counts['stalled'] > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($counts['stalled'] > 0 ? 'danger' : ($counts['in_review'] > 0 ? 'warning' : 'gray'))
                ->icon('heroicon-o-clock')
                ->url($listUrl.'?activeTab=my_contracts&tableFilters[status][value]='.Contract::STATUS_IN_REVIEW->value),

            Stat::make(__('app.stats.my_drafts'), $counts['drafts'])
                ->description(__('app.stats.drafts_description'))
                ->descriptionIcon('heroicon-m-pencil-square')
                ->color($counts['drafts'] > 0 ? 'info' : 'gray')
                ->icon('heroicon-o-pencil-square')
                ->url($listUrl.'?activeTab=my_contracts&tableFilters[status][value]='.Contract::STATUS_DRAFT->value),
        ];
    }
}
