<?php

namespace App\Filament\Widgets\Logger;

use Illuminate\Contracts\Support\Htmlable;
use MrAdder\FilamentLogger\Widgets\TopEventsChartWidget as BaseWidget;

class TopEventsChartWidget extends BaseWidget
{
    public function getHeading(): string|Htmlable|null
    {
        return $this->activityReviewHeadingForPlaybook(__('app.activity.top_events_heading'), 'all_activity');
    }

    protected function getData(): array
    {
        $data = parent::getData();

        if (isset($data['datasets'][0])) {
            $data['datasets'][0]['label'] = __('app.activity.events');
        }

        return $data;
    }
}
