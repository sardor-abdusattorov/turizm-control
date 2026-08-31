<?php

namespace App\Filament\Widgets\Dashboard;

use App\Filament\Pages\Dashboard;
use App\Filament\Resources\Projects\BaseProjectResource;
use App\Models\Contract;
use App\Models\Project;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;

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
                ['icon' => 'heroicon-m-document-text', 'label' => __('app.label.contracts'), 'value' => $project->contracts()->visibleTo()->count()],
                ['icon' => 'heroicon-m-user-group', 'label' => __('app.label.participants'), 'value' => $active($project->feeContracts())->count()],
                ['icon' => 'heroicon-m-star', 'label' => __('app.label.sponsors'), 'value' => $active($project->sponsorshipContracts())->count()],
            ],
        ];
    }
}
