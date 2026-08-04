<?php

namespace App\Filament\Resources\PressTours\Pages;

use App\Enums\PressTourDirection;
use App\Filament\Resources\PressTours\PressTourResource;
use App\Models\PressTour;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListPressTours extends ListRecords
{
    protected static string $resource = PressTourResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /**
     * The registry's three sections, kept as tabs so the list opens the way
     * the buyruq reads: everything, then hosted / domestic / sent abroad.
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $tabs = [
            'all' => Tab::make(__('app.label.all'))
                ->badge(PressTour::query()->count()),
        ];

        foreach (PressTourDirection::cases() as $direction) {
            $tabs[$direction->value] = Tab::make($direction->label())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('direction', $direction->value))
                ->badge(PressTour::query()->where('direction', $direction->value)->count());
        }

        return $tabs;
    }
}
