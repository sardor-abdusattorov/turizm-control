<?php

namespace App\Filament\Resources\Requisitions\Pages;

use App\Enums\RequisitionStatus;
use App\Filament\Resources\Requisitions\RequisitionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListRequisitions extends ListRecords
{
    protected static string $resource = RequisitionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $tabs = [
            'all' => Tab::make(__('app.label.all'))
                ->badge(fn (): int => $this->countBy()),
        ];

        foreach (RequisitionStatus::cases() as $status) {
            $tabs[$status->value] = Tab::make($status->label())
                ->icon($status->icon())
                ->badge(fn (): int => $this->countBy($status))
                ->badgeColor($status->color())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', $status));
        }

        return $tabs;
    }

    private function countBy(?RequisitionStatus $status = null): int
    {
        return static::getResource()::getEloquentQuery()
            ->when($status, fn (Builder $query) => $query->where('status', $status))
            ->count();
    }
}
