<?php

namespace App\Filament\Widgets\Dashboard;

use App\Enums\ProjectType;
use App\Filament\Resources\Projects\BaseProjectResource;
use App\Filament\Support\WidgetPermission;
use App\Models\Project;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class UpcomingProjectsWidget extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 7;

    public static function canView(): bool
    {
        return WidgetPermission::allows(static::class)
            && self::baseQuery()->exists();
    }

    public function getTableHeading(): string
    {
        return __('app.dashboard.upcoming_projects');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => self::baseQuery()->withCount('participants'))
            ->queryStringIdentifier('upcomingProjects')
            ->paginated([5, 10])
            ->defaultPaginationPageOption(5)
            ->columns([
                TextColumn::make('name')
                    ->label(__('app.label.project_name'))
                    ->weight('semibold')
                    ->sortable(false),

                TextColumn::make('type')
                    ->label(__('app.label.project_type'))
                    ->badge()
                    ->formatStateUsing(fn (ProjectType $state): string => $state->label())
                    ->color(fn (ProjectType $state): string => $state->color()),

                TextColumn::make('starts_on')
                    ->label(__('app.label.starts_on'))
                    ->date('d.m.Y'),

                TextColumn::make('ends_on')
                    ->label(__('app.label.ends_on'))
                    ->date('d.m.Y'),

                TextColumn::make('participants_count')
                    ->label(__('app.label.participants'))
                    ->badge()
                    ->color('gray')
                    ->alignCenter(),
            ])
            ->recordUrl(fn (Project $record) => BaseProjectResource::resourceFor($record)::getUrl('view', ['record' => $record]))
            ->emptyStateHeading(__('app.dashboard.upcoming_projects_empty'))
            ->emptyStateIcon('heroicon-o-calendar-days');
    }

    private static function baseQuery(): Builder
    {
        return Project::query()
            ->where('status', true)
            ->whereNotNull('starts_on')
            ->whereDate('starts_on', '>=', today())
            ->orderBy('starts_on');
    }
}
