<?php

namespace App\Filament\Widgets\Logger;

use Illuminate\Contracts\Support\Htmlable;
use MrAdder\FilamentLogger\Widgets\HighRiskActionsChartWidget as BaseWidget;

class HighRiskActionsChartWidget extends BaseWidget
{
    public function getHeading(): string|Htmlable|null
    {
        return $this->activityReviewHeadingForPlaybook(__('app.activity.high_risk_actions_heading'), 'high_risk_incidents');
    }

    protected function getData(): array
    {
        $data = parent::getData();

        if (isset($data['datasets'][0])) {
            $data['datasets'][0]['label'] = __('app.activity.high_risk_events');
        }

        return $data;
    }
}
