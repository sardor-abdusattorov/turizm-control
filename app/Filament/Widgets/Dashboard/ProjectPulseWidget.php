<?php

namespace App\Filament\Widgets\Dashboard;

use App\Models\Project;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;

/**
 * «Проект на ладони» — the director's one-glance card for the project picked
 * in the dashboard filter (defaulting to the nearest upcoming one): dates,
 * money, participants and the freshest contracts, without leaving the page.
 *
 * Contract money and lists go through visibleTo(): a manager only ever sees
 * their own contracts here, same as everywhere else.
 */
class ProjectPulseWidget extends Widget
{
    use InteractsWithPageFilters;

    protected string $view = 'filament.widgets.dashboard.project-pulse';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = -10;

    public static function canView(): bool
    {
        return auth()->user()?->can('view_any_project') ?? false;
    }

    public function project(): ?Project
    {
        $id = $this->pageFilters['project_id'] ?? null;

        $project = $id
            ? Project::query()->find($id)
            : null;

        return $project ?? Project::dashboardDefault();
    }
}
