<?php

namespace App\Filament\Widgets\Logger;

use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use MrAdder\FilamentLogger\Widgets\ActivityOverviewWidget as BaseWidget;

class ActivityOverviewWidget extends BaseWidget
{
    use HasWidgetShield;

    public function getHeading(): ?string
    {
        return __('app.activity.overview_heading');
    }

    protected function getStats(): array
    {
        $stats = parent::getStats();

        $labels = [
            ['label' => __('app.activity.total_activity'), 'description' => __('app.activity.last_days', ['days' => $this->days])],
            ['label' => __('app.activity.high_risk'),      'description' => __('app.activity.high_risk_description')],
            ['label' => __('app.activity.failed_logins'),  'description' => __('app.activity.failed_logins_description')],
            ['label' => __('app.activity.unique_actors'),  'description' => __('app.activity.unique_actors_description')],
        ];

        foreach ($stats as $i => $stat) {
            if (isset($labels[$i])) {
                $stat->label($labels[$i]['label'])->description($labels[$i]['description']);
            }
        }

        return $stats;
    }
}
