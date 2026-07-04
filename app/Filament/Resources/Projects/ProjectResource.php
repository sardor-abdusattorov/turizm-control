<?php

namespace App\Filament\Resources\Projects;

use App\Enums\ProjectType;
use App\Filament\Resources\Projects\Pages\CreateProject;
use App\Filament\Resources\Projects\Pages\EditProject;
use App\Filament\Resources\Projects\Pages\ListProjects;
use App\Filament\Resources\Projects\Pages\ViewProject;
use App\Filament\Resources\Projects\Schemas\ProjectForm;
use App\Filament\Resources\Projects\Tables\ProjectsTable;
use App\Models\Project;
use BackedEnum;
use Filament\Navigation\NavigationItem;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ProjectResource extends Resource
{
    protected static ?string $model = Project::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartBar;

    public static function getNavigationGroup(): ?string
    {
        return __('app.label.projects');
    }

    public static function getModelLabel(): string
    {
        return __('app.label.project_single');
    }

    public static function getPluralModelLabel(): string
    {
        return __('app.label.project_plural');
    }

    /**
     * The sidebar shows the two project kinds as separate entries (Internal /
     * International), each landing on the list pre-filtered to its tab —
     * instead of one generic "Projects" item.
     *
     * @return array<NavigationItem>
     */
    public static function getNavigationItems(): array
    {
        return [
            self::navigationItemFor(ProjectType::Internal, Heroicon::OutlinedBuildingOffice2, 1),
            self::navigationItemFor(ProjectType::International, Heroicon::OutlinedGlobeAlt, 2),
        ];
    }

    private static function navigationItemFor(ProjectType $type, Heroicon $icon, int $sort): NavigationItem
    {
        // ListRecords binds its $activeTab to the URL as `?tab=` (#[Url(as: 'tab')]),
        // so the sidebar links must use that exact query key to land on a
        // pre-filtered tab.
        return NavigationItem::make("projects-{$type->value}")
            ->label(fn (): string => __('app.label.projects_'.$type->value))
            ->icon($icon)
            ->group(static::getNavigationGroup())
            ->sort($sort)
            ->url(static::getUrl('index', ['tab' => $type->value]))
            ->isActiveWhen(fn (): bool => request()->routeIs(static::getRouteBaseName().'.*')
                && request()->query('tab') === $type->value)
            ->badge(fn (): ?string => ($count = Project::query()->where('type', $type)->count()) > 0
                ? (string) $count
                : null)
            ->visible(fn (): bool => static::canViewAny());
    }

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return ProjectForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProjectsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProjects::route('/'),
            'create' => CreateProject::route('/create'),
            'view' => ViewProject::route('/{record}'),
            'edit' => EditProject::route('/{record}/edit'),
        ];
    }
}
