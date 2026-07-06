<?php

namespace App\Filament\Resources\Projects;

use App\Enums\ProjectType;
use App\Filament\Resources\Projects\Pages\CreateInternalProject;
use App\Filament\Resources\Projects\Pages\EditInternalProject;
use App\Filament\Resources\Projects\Pages\ListInternalProjects;
use App\Filament\Resources\Projects\Pages\ViewInternalProject;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

class InternalProjectResource extends BaseProjectResource
{
    protected static ?string $slug = 'internal-projects';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    public static function projectType(): ProjectType
    {
        return ProjectType::Internal;
    }

    public static function getNavigationLabel(): string
    {
        return __('app.label.projects_internal');
    }

    public static function getModelLabel(): string
    {
        return __('app.label.internal_project_single');
    }

    public static function getPluralModelLabel(): string
    {
        return __('app.label.internal_project_plural');
    }

    public static function getNavigationSort(): int
    {
        return 1;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInternalProjects::route('/'),
            'create' => CreateInternalProject::route('/create'),
            'view' => ViewInternalProject::route('/{record}'),
            'edit' => EditInternalProject::route('/{record}/edit'),
        ];
    }
}
