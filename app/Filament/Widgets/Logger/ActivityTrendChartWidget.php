<?php

namespace App\Filament\Widgets\Logger;

use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Illuminate\Contracts\Support\Htmlable;
use MrAdder\FilamentLogger\Widgets\ActivityTrendChartWidget as BaseWidget;

class ActivityTrendChartWidget extends BaseWidget
{
    use HasWidgetShield;

    public function getHeading(): string|Htmlable|null
    {
        return $this->activityReviewHeadingForPlaybook(__('app.activity.activity_trend_heading'), 'all_activity');
    }

    protected function getData(): array
    {
        $data = parent::getData();

        if (isset($data['datasets'][0])) {
            $data['datasets'][0]['label'] = __('app.activity.trend_dataset');
        }

        return $data;
    }
}
