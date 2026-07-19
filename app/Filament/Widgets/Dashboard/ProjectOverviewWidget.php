<?php

namespace App\Filament\Widgets\Dashboard;

use App\Filament\Pages\Dashboard;
use App\Filament\Resources\Projects\BaseProjectResource;
use App\Models\Contract;
use App\Models\Project;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;

/**
 * The strip between the greeting and the numbers: the picked project's name,
 * dates and counts on the left, the «Фильтры» button on the right — it mounts
 * the page's FilterAction slide-over.
 */
class ProjectOverviewWidget extends Widget
{
    use InteractsWithPageFilters;

    protected string $view = 'filament.widgets.dashboard.project-overview';

    protected static ?int $sort = -15;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->can('view_any_project') ?? false;
    }

    public function project(): ?Project
    {
        $projectId = Dashboard::filterValue($this->pageFilters['projectId'] ?? null);

        return $projectId ? Project::query()->find($projectId) : null;
    }

    public function hasActiveFilters(): bool
    {
        return Dashboard::filterValue($this->pageFilters['type'] ?? null) !== null
            || Dashboard::filterValue($this->pageFilters['year'] ?? null) !== null;
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $project = $this->project();

        if (! $project) {
            return [
                'project' => null,
                'projectUrl' => null,
                'counts' => [],
            ];
        }

        $active = fn ($query) => $query->visibleTo()
            ->where('status', '!=', Contract::STATUS_REJECTED->value);

        return [
            'project' => $project,
            'projectUrl' => BaseProjectResource::resourceFor($project)::getUrl('view', ['record' => $project]),
            'counts' => [
                __('app.label.contracts') => $project->contracts()->visibleTo()->count(),
                __('app.label.participants') => $active($project->feeContracts())->count(),
                __('app.label.sponsors') => $active($project->sponsorshipContracts())->count(),
            ],
        ];
    }
}
