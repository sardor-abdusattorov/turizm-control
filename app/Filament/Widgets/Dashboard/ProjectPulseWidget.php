<?php

namespace App\Filament\Widgets\Dashboard;

use App\Models\Project;
use Filament\Widgets\Widget;

/**
 * «Обзор проекта» — the director's one-glance card for the picked project
 * (defaulting to the nearest upcoming one): dates, money, participants and
 * the freshest contracts, without leaving the page.
 *
 * The picker is part of the card itself — a styled select in its toolbar —
 * and the choice is remembered in the session, so the page carries no
 * separate filters form above the greeting.
 *
 * Contract money and lists go through visibleTo(): a manager only ever sees
 * their own contracts here, same as everywhere else.
 */
class ProjectPulseWidget extends Widget
{
    private const SESSION_KEY = 'dashboard.project_id';

    protected string $view = 'filament.widgets.dashboard.project-pulse';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = -10;

    public ?int $projectId = null;

    public static function canView(): bool
    {
        return auth()->user()?->can('view_any_project') ?? false;
    }

    public function mount(): void
    {
        $remembered = session(self::SESSION_KEY);

        $this->projectId = $remembered && Project::query()->whereKey($remembered)->exists()
            ? (int) $remembered
            : Project::dashboardDefault()?->id;
    }

    public function updatedProjectId(mixed $value): void
    {
        $this->projectId = filled($value) ? (int) $value : null;

        session()->put(self::SESSION_KEY, $this->projectId);
    }

    public function project(): ?Project
    {
        $project = $this->projectId
            ? Project::query()->find($this->projectId)
            : null;

        return $project ?? Project::dashboardDefault();
    }

    /**
     * Active projects as «тип · год» optgroups for the toolbar select.
     *
     * @return array<string, array<int, string>>
     */
    public function projectOptions(): array
    {
        return Project::groupedOptions();
    }
}
