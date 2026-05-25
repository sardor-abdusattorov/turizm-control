<?php

namespace App\Filament\Widgets\Logger;

use Illuminate\Contracts\Support\Htmlable;
use MrAdder\FilamentLogger\Widgets\TopUsersChartWidget as BaseWidget;

class TopUsersChartWidget extends BaseWidget
{
    public function getHeading(): string|Htmlable|null
    {
        return $this->activityReviewHeadingForPlaybook(__('Top Users'), 'all_activity');
    }

    protected function getData(): array
    {
        $data = parent::getData();

        if (isset($data['datasets'][0])) {
            $data['datasets'][0]['label'] = __('Events');
        }

        return $data;
    }
}
