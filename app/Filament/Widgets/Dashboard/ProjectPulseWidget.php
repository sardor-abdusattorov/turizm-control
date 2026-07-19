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
 * The toolbar keeps one visible control — the project picker — flanked by
 * prev/next arrows that step through the filtered list in dropdown order.
 * The type/year refinements hide behind a funnel button, index-style, and
 * only narrow the list. The choice is remembered in the session.
 *
 * Contract money and lists go through visibleTo(): a manager only ever sees
 * their own contracts here, same as everywhere else. The picker only changes
 * which project is shown; it never widens that visibility.
 */
class ProjectPulseWidget extends Widget implements HasSchemas
{
    use InteractsWithSchemas;

    private const SESSION_KEY = 'dashboard.project_id';

    private const ALL = 'all';

    protected string $view = 'filament.widgets.dashboard.project-pulse';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = -10;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    /** @var array<string, mixed>|null */
    public ?array $filters = [];

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

        $this->filtersForm->fill([
            'type' => self::ALL,
            'year' => self::ALL,
        ]);

        $this->form->fill(['projectId' => $projectId]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('projectId')
                    ->hiddenLabel()
                    ->options(fn (): array => Project::groupedOptions($this->yearFilter(), $this->typeFilter()))
                    ->searchable()
                    ->preload()
                    ->selectablePlaceholder(false)
                    // Keep the closed control one line tall — long project
                    // names ellipsize instead of wrapping the toolbar. The
                    // OPEN list still wraps them in full (see theme.css).
                    ->wrapOptionLabels(false)
                    ->live()
                    ->afterStateUpdated(fn (?string $state) => $this->persist($state)),
            ])
            ->statePath('data');
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('type')
                    ->label(__('app.label.project_type'))
                    ->options([
                        self::ALL => __('app.label.all'),
                        'internal' => __('app.label.projects_internal'),
                        'international' => __('app.label.projects_international'),
                    ])
                    ->default(self::ALL)
                    ->native(false)
                    ->selectablePlaceholder(false)
                    ->live()
                    ->afterStateUpdated(fn () => $this->rehomeSelection()),

                Select::make('year')
                    ->label(__('app.label.year'))
                    ->options(self::yearOptions())
                    ->default(self::ALL)
                    ->native(false)
                    ->selectablePlaceholder(false)
                    ->live()
                    ->afterStateUpdated(fn () => $this->rehomeSelection()),
            ])
            ->statePath('filters');
    }

    /**
     * Strictly the picked project — no silent fallback: when the filters
     * leave the selection empty, the widget shows the pick-a-project prompt
     * instead of a project the filters never matched.
     */
    public function project(): ?Project
    {
        $projectId = data_get($this->data, 'projectId');

        return $projectId
            ? Project::query()->find($projectId)
            : null;
    }

    /**
     * The prev/next arrows: step through the filtered project list in the
     * same order the dropdown shows, wrapping at both ends.
     */
    public function stepProject(int $step): void
    {
        $ids = Project::filteredIds($this->typeFilter(), $this->yearFilter());

        if ($ids === []) {
            return;
        }

        $current = array_search((int) data_get($this->data, 'projectId'), $ids, true);

        $next = $current === false
            ? $ids[0]
            : $ids[($current + $step + count($ids)) % count($ids)];

        $this->data['projectId'] = $next;
        $this->persist($next);
    }

    /**
     * Feeds the dot on the funnel button — visible only while the list is
     * actually narrowed.
     */
    public function hasActiveFilters(): bool
    {
        return $this->typeFilter() !== null || $this->yearFilter() !== null;
    }

    /**
     * When a filter changes, keep the shown project if it still matches;
     * otherwise CLEAR the selection — filters first, then the user picks a
     * project from the narrowed list. Nothing is auto-chosen for them.
     */
    protected function rehomeSelection(): void
    {
        $ids = Project::filteredIds($this->typeFilter(), $this->yearFilter());

        if (in_array((int) data_get($this->data, 'projectId'), $ids, true)) {
            return;
        }

        $this->data['projectId'] = null;
        $this->persist(null);
    }

    protected function persist(mixed $state): void
    {
        session()->put(self::SESSION_KEY, filled($state) ? (int) $state : null);
    }

    protected function typeFilter(): ?string
    {
        return self::filterValue(data_get($this->filters, 'type'));
    }

    protected function yearFilter(): ?string
    {
        return self::filterValue(data_get($this->filters, 'year'));
    }

    /**
     * The «Все» sentinel means "no filter" — map it to null for the query.
     */
    protected static function filterValue(mixed $state): ?string
    {
        return filled($state) && $state !== self::ALL ? (string) $state : null;
    }

    /**
     * @return array<string, string>
     */
    protected static function yearOptions(): array
    {
        $years = [];

        foreach (Project::pickerYears() as $year) {
            $years[$year] = $year;
        }

        return [self::ALL => __('app.label.all')] + $years;
    }
}
