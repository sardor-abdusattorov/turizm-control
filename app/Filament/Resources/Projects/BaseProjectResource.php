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

/**
 * Internal and international projects are two full-fledged sidebar resources
 * over the same Project model: each subclass pins its ProjectType, scopes its
 * queries to it and stamps it on create — no type field, no list tabs.
 * Filament Shield keys permissions by model, so both share the *_project set.
 */
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

    /**
     * The concrete resource a record belongs to — for links rendered in
     * shared contexts (table recordUrl, breakdown modals).
     *
     * @return class-string<self>
     */
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
