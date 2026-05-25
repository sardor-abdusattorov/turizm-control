<?php

namespace App\Filament\Widgets\Logger;

use MrAdder\FilamentLogger\Widgets\ActivityOverviewWidget as BaseWidget;

class ActivityOverviewWidget extends BaseWidget
{
    public function getHeading(): ?string
    {
        return __('Activity Overview');
    }

    protected function getStats(): array
    {
        $stats = parent::getStats();

        $labels = [
            ['label' => __('Total Activity'),  'description' => __('Last :days days', ['days' => $this->days])],
            ['label' => __('High Risk'),       'description' => __('High-risk actions detected')],
            ['label' => __('Failed Logins'),   'description' => __('Authentication failures')],
            ['label' => __('Unique Actors'),   'description' => __('Distinct causers recorded')],
        ];

        foreach ($stats as $i => $stat) {
            if (isset($labels[$i])) {
                $stat->label($labels[$i]['label'])->description($labels[$i]['description']);
            }
        }

        return $stats;
    }
}
