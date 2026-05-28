<?php

namespace App\Filament\Resources\Contracts\Pages;

use App\Filament\Resources\Contracts\ContractResource;
use App\Models\Contract;
use Filament\Actions\CreateAction;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListContracts extends ListRecords
{
    protected static string $resource = ContractResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        $awaitingCount = Contract::query()->awaitingApprovalBy()->count();
        $myCount = Contract::query()->where('responsible_id', auth()->id())->count();

        return [
            'all' => Tab::make(__('app.tab.all_contracts')),

            'awaiting_me' => Tab::make(__('app.tab.awaiting_me'))
                ->badge($awaitingCount > 0 ? $awaitingCount : null)
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->awaitingApprovalBy()),

            'mine' => Tab::make(__('app.tab.my_contracts'))
                ->badge($myCount > 0 ? $myCount : null)
                ->modifyQueryUsing(fn (Builder $query) => $query->where('responsible_id', auth()->id())),

            'involving_me' => Tab::make(__('app.tab.involving_me'))
                ->modifyQueryUsing(fn (Builder $query) => $query->involvingApprover()),
        ];
    }
}
