<?php

namespace App\Filament\Resources\ActivityResource\Pages;

use App\Filament\Widgets\Logger\ActivityOverviewWidget;
use App\Filament\Widgets\Logger\ActivityTrendChartWidget;
use App\Filament\Widgets\Logger\HighRiskActionsChartWidget;
use App\Filament\Widgets\Logger\TopEventsChartWidget;
use App\Filament\Widgets\Logger\TopUsersChartWidget;
use MrAdder\FilamentLogger\Resources\ActivityResource\Pages\ListActivities as BaseListActivities;

class ListActivities extends BaseListActivities
{
    protected function getHeaderWidgets(): array
    {
        if (! config('filament-logger.dashboard.enabled', true)) {
            return [];
        }

        $days = (int) config('filament-logger.dashboard.lookback_days', 30);
        $limit = (int) config('filament-logger.dashboard.top_limit', 5);

        return [
            ActivityOverviewWidget::make(['days' => $days]),
            ActivityTrendChartWidget::make(['days' => $days]),
            TopUsersChartWidget::make(['days' => $days, 'limit' => $limit]),
            TopEventsChartWidget::make(['days' => $days, 'limit' => $limit]),
            HighRiskActionsChartWidget::make(['days' => $days, 'limit' => $limit]),
        ];
    }
}
