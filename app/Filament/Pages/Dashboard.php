<?php

namespace App\Filament\Pages;

use App\Models\Project;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Schema;

/**
 * The greeting and contextual line live in the DashboardHeaderWidget card —
 * together with the Telegram connect prompt — so the page itself keeps the
 * plain Filament header.
 */
class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    /**
     * The header card and the project pulse are both full-width, so a single
     * column is all the grid the page needs.
     */
    public function getColumns(): int|array
    {
        return 1;
    }

    /**
     * The project picker the director asked for: one select above the
     * widgets, defaulting to the nearest upcoming project; the choice is
     * remembered in the session (HasFilters persists it).
     */
    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('project_id')
                    ->label(__('app.label.project_single'))
                    ->options(fn (): array => Project::groupedOptions())
                    ->default(fn (): ?int => Project::dashboardDefault()?->id)
                    ->searchable()
                    ->selectablePlaceholder(false)
                    ->visible(fn (): bool => auth()->user()?->can('view_any_project') ?? false),
            ]);
    }
}
