<?php

namespace App\Filament\Resources\Contracts\Pages;

use App\Filament\Resources\Contracts\ContractResource;
use App\Models\Contract;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
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

        $tabs = [];

        if (auth()->user()?->hasAnyRole(Contract::OVERSIGHT_ROLES)) {
            $tabs['all'] = Tab::make(__('app.tab.all_contracts'))
                ->icon('heroicon-o-rectangle-stack');
        }

        $tabs['my_contracts'] = Tab::make(__('app.tab.my_contracts'))
            ->icon('heroicon-o-user')
            ->modifyQueryUsing(fn (Builder $query) => $query->where('responsible_id', auth()->id()));

        $tabs['awaiting_me'] = Tab::make(__('app.tab.awaiting_me'))
            ->icon('heroicon-o-inbox-arrow-down')
            ->modifyQueryUsing(fn (Builder $query) => $query->awaitingApprovalBy())
            ->badge($awaitingCount > 0 ? $awaitingCount : null)
            ->badgeColor('warning');

        $tabs['involving_me'] = Tab::make(__('app.tab.involving_me'))
            ->icon('heroicon-o-users')
            ->modifyQueryUsing(fn (Builder $query) => $query->involvingApprover());

        return $tabs;
    }

    public function getDefaultActiveTab(): string|int|null
    {
        $user = auth()->user();

        if ($user && Contract::query()->awaitingApprovalBy($user)->exists()) {
            return 'awaiting_me';
        }

        if ($user?->hasAnyRole(Contract::OVERSIGHT_ROLES)) {
            return 'all';
        }

        return 'my_contracts';
    }
}
