<?php

namespace App\Filament\Widgets\Logger;

use Illuminate\Contracts\Support\Htmlable;
use MrAdder\FilamentLogger\Widgets\ActivityTrendChartWidget as BaseWidget;

class ActivityTrendChartWidget extends BaseWidget
{
    public function getHeading(): string|Htmlable|null
    {
        return $this->activityReviewHeadingForPlaybook(__('Activity Trend'), 'all_activity');
    }

    protected function getData(): array
    {
        $data = parent::getData();

        if (isset($data['datasets'][0])) {
            $data['datasets'][0]['label'] = __('Activity');
        }

        return $data;
    }
}
