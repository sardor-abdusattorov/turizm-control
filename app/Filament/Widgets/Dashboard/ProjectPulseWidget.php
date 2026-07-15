<?php

namespace App\Filament\Widgets\Dashboard;

use App\Models\Project;
use Filament\Forms\Components\Select;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Widgets\Widget;

/**
 * «Обзор проекта» — the director's one-glance card for the picked project
 * (defaulting to the nearest upcoming one): dates, money, participants and
 * the freshest contracts, without leaving the page.
 *
 * The picker is a real Filament Select embedded in the card toolbar — the
 * same Choices-style dropdown used everywhere else in the app, not a native
 * <select> — and the choice is remembered in the session, so the page carries
 * no separate filters form above the greeting.
 *
 * Contract money and lists go through visibleTo(): a manager only ever sees
 * their own contracts here, same as everywhere else. The picker only changes
 * which project is shown; it never widens that visibility.
 */
class ProjectPulseWidget extends Widget implements HasSchemas
{
    use InteractsWithSchemas;

    private const SESSION_KEY = 'dashboard.project_id';

    protected string $view = 'filament.widgets.dashboard.project-pulse';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = -10;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canView(): bool
    {
        return auth()->user()?->can('view_any_project') ?? false;
    }

    public function mount(): void
    {
        $remembered = session(self::SESSION_KEY);

        $projectId = $remembered && Project::query()->whereKey($remembered)->exists()
            ? (int) $remembered
            : Project::dashboardDefault()?->id;

        $this->form->fill(['projectId' => $projectId]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('projectId')
                    ->label(__('app.label.project_single'))
                    ->hiddenLabel()
                    ->options(Project::groupedOptions())
                    ->searchable()
                    ->preload()
                    ->selectablePlaceholder(false)
                    // Keep the closed control one line tall — long project names
                    // ellipsize instead of wrapping the toolbar into three rows.
                    ->wrapOptionLabels(false)
                    ->live()
                    ->afterStateUpdated(function (?string $state): void {
                        session()->put(
                            self::SESSION_KEY,
                            filled($state) ? (int) $state : null,
                        );
                    }),
            ])
            ->statePath('data');
    }

    public function project(): ?Project
    {
        $projectId = data_get($this->data, 'projectId');

        $project = $projectId
            ? Project::query()->find($projectId)
            : null;

        return $project ?? Project::dashboardDefault();
    }
}
