<?php

namespace App\Filament\Widgets\Dashboard;

use App\Models\Project;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Widgets\Widget;

/**
 * «Обзор проекта» — the director's one-glance card for the picked project
 * (defaulting to the nearest upcoming one): dates, money, participants and
 * the freshest contracts, without leaving the page.
 *
 * The picker toolbar carries two quick filters — type (all / internal /
 * international) and year — that narrow the project list; picking a project,
 * or changing a filter, updates the card in place. The choice is remembered
 * in the session, so the page carries no separate filters form above the
 * greeting.
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

        $this->form->fill([
            'type' => self::ALL,
            'year' => self::ALL,
            'projectId' => $projectId,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(['default' => 1, 'md' => 12])
                    ->schema([
                        ToggleButtons::make('type')
                            ->label(__('app.label.project_type'))
                            ->options([
                                self::ALL => __('app.label.all'),
                                'internal' => __('app.label.projects_internal'),
                                'international' => __('app.label.projects_international'),
                            ])
                            ->default(self::ALL)
                            ->grouped()
                            ->live()
                            ->afterStateUpdated(fn (Get $get, Set $set) => $this->rehomeSelection($get, $set))
                            ->columnSpan(['default' => 1, 'md' => 5]),

                        ToggleButtons::make('year')
                            ->label(__('app.label.year'))
                            ->options(self::yearOptions())
                            ->default(self::ALL)
                            ->grouped()
                            ->live()
                            ->afterStateUpdated(fn (Get $get, Set $set) => $this->rehomeSelection($get, $set))
                            ->columnSpan(['default' => 1, 'md' => 3]),

                        Select::make('projectId')
                            ->label(__('app.label.project_single'))
                            ->options(fn (Get $get): array => Project::groupedOptions(
                                self::filterValue($get('year')),
                                self::filterValue($get('type')),
                            ))
                            ->searchable()
                            ->preload()
                            ->selectablePlaceholder(false)
                            // Keep the closed control one line tall — long project
                            // names ellipsize instead of wrapping the toolbar.
                            ->wrapOptionLabels(false)
                            ->live()
                            ->afterStateUpdated(fn (?string $state) => $this->persist($state))
                            ->columnSpan(['default' => 1, 'md' => 4]),
                    ]),
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

    /**
     * When a filter changes, keep the shown project if it still matches;
     * otherwise jump to the first project in the narrowed set so the card
     * never shows a project that falls outside the active filters.
     */
    protected function rehomeSelection(Get $get, Set $set): void
    {
        $ids = Project::filteredIds(
            self::filterValue($get('type')),
            self::filterValue($get('year')),
        );

        if (in_array((int) $get('projectId'), $ids, true)) {
            return;
        }

        $next = $ids[0] ?? null;
        $set('projectId', $next);
        $this->persist($next);
    }

    protected function persist(mixed $state): void
    {
        session()->put(self::SESSION_KEY, filled($state) ? (int) $state : null);
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
