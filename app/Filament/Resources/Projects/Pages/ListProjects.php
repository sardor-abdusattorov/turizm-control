<?php

namespace App\Filament\Resources\Projects\Pages;

use App\Enums\ProjectType;
use App\Filament\Resources\Projects\ProjectResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListProjects extends ListRecords
{
    protected static string $resource = ProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make(__('app.tab.all_projects'))
                ->icon('heroicon-o-rectangle-stack'),

            ProjectType::Internal->value => Tab::make(ProjectType::Internal->label())
                ->icon('heroicon-o-building-office-2')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('type', ProjectType::Internal)),

            ProjectType::International->value => Tab::make(ProjectType::International->label())
                ->icon('heroicon-o-globe-alt')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('type', ProjectType::International)),
        ];
    }
}
