<?php

namespace App\Filament\Resources\Projects;

use App\Enums\ProjectType;
use App\Filament\Resources\Projects\Pages\CreateInternationalProject;
use App\Filament\Resources\Projects\Pages\EditInternationalProject;
use App\Filament\Resources\Projects\Pages\ListInternationalProjects;
use App\Filament\Resources\Projects\Pages\ViewInternationalProject;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

class InternationalProjectResource extends BaseProjectResource
{
    protected static ?string $slug = 'international-projects';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    public static function projectType(): ProjectType
    {
        return ProjectType::International;
    }

    public static function getNavigationLabel(): string
    {
        return __('app.label.international_project_plural');
    }

    public static function getModelLabel(): string
    {
        return __('app.label.international_project_single');
    }

    public static function getPluralModelLabel(): string
    {
        return __('app.label.international_project_plural');
    }

    public static function getNavigationSort(): int
    {
        return 2;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInternationalProjects::route('/'),
            'create' => CreateInternationalProject::route('/create'),
            'view' => ViewInternationalProject::route('/{record}'),
            'edit' => EditInternationalProject::route('/{record}/edit'),
        ];
    }
}
