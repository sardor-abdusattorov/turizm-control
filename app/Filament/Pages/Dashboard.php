<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\Dashboard\DashboardHeaderWidget;
use App\Models\Project;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard\Actions\FilterAction;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersAction;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Contracts\View\View;

class Dashboard extends BaseDashboard
{
    use HasFiltersAction;

    public const ALL = 'all';

    public function mount(): void
    {
        $this->filters ??= [];
        $this->filters['projectId'] ??= Project::dashboardDefault()?->id;
        $this->filters['type'] ??= self::ALL;
        $this->filters['year'] ??= self::ALL;
    }

    /** @return array<int, class-string> */
    public function getWidgets(): array
    {
        return [DashboardHeaderWidget::class];
    }

    protected function getHeaderActions(): array
    {
        return [
            FilterAction::make()
                ->label(__('app.label.filters'))
                ->schema([
                    Select::make('type')
                        ->label(__('app.label.project_type'))
                        ->options([
                            self::ALL => __('app.label.all'),
                            'internal' => __('app.label.projects_internal'),
                            'international' => __('app.label.projects_international'),
                        ])
                        ->default(self::ALL)
                        ->selectablePlaceholder(false)
                        ->live()
                        ->afterStateUpdated(fn (Set $set) => $set('projectId', null)),

                    Select::make('year')
                        ->label(__('app.label.year'))
                        ->options(self::yearOptions())
                        ->default(self::ALL)
                        ->selectablePlaceholder(false)
                        ->live()
                        ->afterStateUpdated(fn (Set $set) => $set('projectId', null)),

                    Select::make('projectId')
                        ->label(__('app.label.project_single'))
                        ->options(fn (Get $get): array => Project::groupedOptions(
                            self::filterValue($get('year')),
                            self::filterValue($get('type')),
                        ))
                        ->searchable()
                        ->preload(),
                ]),
        ];
    }

    public function getColumns(): int|array
    {
        return 2;
    }

    public function getHeader(): ?View
    {
        return view('filament.pages.dashboard-blank-header');
    }

    /** @return array<string, string> */
    public static function yearOptions(): array
    {
        $years = [];

        foreach (Project::pickerYears() as $year) {
            $years[$year] = (string) $year;
        }

        return [self::ALL => __('app.label.all_years')] + $years;
    }

    public static function filterValue(mixed $state): ?string
    {
        return blank($state) || $state === self::ALL ? null : (string) $state;
    }
}
