<?php

namespace App\Filament\Widgets\Logger;

use Illuminate\Contracts\Support\Htmlable;
use MrAdder\FilamentLogger\Widgets\HighRiskActionsChartWidget as BaseWidget;

class HighRiskActionsChartWidget extends BaseWidget
{
    public function getHeading(): string|Htmlable|null
    {
        return $this->activityReviewHeadingForPlaybook(__('High-Risk Actions'), 'high_risk_incidents');
    }

    protected function getData(): array
    {
        $data = parent::getData();

        if (isset($data['datasets'][0])) {
            $data['datasets'][0]['label'] = __('High-Risk Events');
        }

        return $data;
    }
}
