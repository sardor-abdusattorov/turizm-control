<?php

namespace App\Filament\Widgets;

use App\Enums\ProjectType;
use App\Filament\Resources\Projects\InternalProjectResource;
use App\Filament\Resources\Projects\InternationalProjectResource;
use App\Filament\Support\WidgetPermission;
use App\Models\Project;
use App\Models\ProjectParticipant;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * The exhibitions block the director asked to see SEPARATELY on the
 * dashboard: this year's internal and international projects, and the
 * year's participation fees with how much has actually been collected.
 */
class ProjectStatsWidget extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    protected static ?int $sort = 4;

    public static function canView(): bool
    {
        return WidgetPermission::allows(static::class);
    }

    protected function getStats(): array
    {
        $year = now()->year;

        $counts = [];
        foreach (ProjectType::cases() as $type) {
            $counts[$type->value] = [
                'year' => Project::query()->where('type', $type)->whereYear('starts_on', $year)->count(),
                'total' => Project::query()->where('type', $type)->count(),
            ];
        }

        $fees = ProjectParticipant::query()
            ->whereHas('project', fn ($query) => $query->whereYear('starts_on', $year))
            ->selectRaw('COALESCE(SUM(amount), 0) as pledged, COALESCE(SUM(paid_amount), 0) as paid')
            ->first();

        return [
            Stat::make(__('app.label.international_project_plural'), (string) $counts[ProjectType::International->value]['year'])
                ->description(__('app.stats.projects_total', ['count' => $counts[ProjectType::International->value]['total']]))
                ->descriptionIcon('heroicon-m-globe-alt')
                ->color('success')
                ->icon('heroicon-o-globe-alt')
                ->url(InternationalProjectResource::getUrl('index')),

            Stat::make(__('app.label.internal_project_plural'), (string) $counts[ProjectType::Internal->value]['year'])
                ->description(__('app.stats.projects_total', ['count' => $counts[ProjectType::Internal->value]['total']]))
                ->descriptionIcon('heroicon-m-building-office-2')
                ->color('info')
                ->icon('heroicon-o-building-office-2')
                ->url(InternalProjectResource::getUrl('index')),

            Stat::make(
                __('app.stats.projects_fees', ['year' => $year]),
                number_format((float) $fees->pledged, 0, ',', ' '),
            )
                ->description(__('app.stats.projects_fees_paid', ['paid' => number_format((float) $fees->paid, 0, ',', ' ')]))
                ->descriptionIcon('heroicon-m-check-circle')
                ->color((float) $fees->paid > 0 ? 'success' : 'gray')
                ->icon('heroicon-o-banknotes'),
        ];
    }
}
