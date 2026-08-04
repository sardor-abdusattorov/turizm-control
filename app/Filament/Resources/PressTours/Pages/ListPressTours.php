<?php

namespace App\Filament\Resources\PressTours\Pages;

use App\Enums\PressTourDirection;
use App\Exports\PressToursExport;
use App\Filament\Resources\PressTours\PressTourResource;
use App\Filament\Support\ExportPermission;
use App\Filament\Support\ExportXlsxAction;
use App\Models\PressTour;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Facades\Excel;

class ListPressTours extends ListRecords
{
    protected static string $resource = PressTourResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // The sheet handed upward once the tours have run; it exports
            // whatever the table currently shows, filters and tab included.
            ExportXlsxAction::make()
                ->visible(fn (): bool => ExportPermission::allows('export_press_tour'))
                ->action(fn ($livewire) => Excel::download(
                    new PressToursExport($livewire->getFilteredTableQuery()),
                    'press-tours-'.now()->format('Y-m-d').'.xlsx',
                )),
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
