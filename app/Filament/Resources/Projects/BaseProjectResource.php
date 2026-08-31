<?php

namespace App\Filament\Resources\Projects;

use App\Enums\ProjectType;
use App\Filament\Resources\Projects\Schemas\ProjectForm;
use App\Filament\Resources\Projects\Tables\ProjectsTable;
use App\Models\Project;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

abstract class BaseProjectResource extends Resource
{
    protected static ?string $model = Project::class;

    protected static ?string $recordTitleAttribute = 'name';

    abstract public static function projectType(): ProjectType;

    public static function getNavigationGroup(): ?string
    {
        return __('app.label.projects');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('type', static::projectType());
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) Project::query()->where('type', static::projectType())->count();
    }

    /** @return class-string<self> */
    public static function resourceFor(Project $record): string
    {
        return $record->type === ProjectType::Internal
            ? InternalProjectResource::class
            : InternationalProjectResource::class;
    }

    public static function urlFor(Project $record): string
    {
        return static::resourceFor($record)::getUrl('view', ['record' => $record]);
    }

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
}
