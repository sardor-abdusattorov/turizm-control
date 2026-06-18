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

        $tabs = [
            'my_contracts' => Tab::make(__('app.tab.my_contracts'))
                ->icon('heroicon-o-user')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('responsible_id', auth()->id())),

            'awaiting_me' => Tab::make(__('app.tab.awaiting_me'))
                ->icon('heroicon-o-inbox-arrow-down')
                ->modifyQueryUsing(fn (Builder $query) => $query->awaitingApprovalBy())
                ->badge($awaitingCount > 0 ? $awaitingCount : null)
                ->badgeColor('warning'),

            'involving_me' => Tab::make(__('app.tab.involving_me'))
                ->icon('heroicon-o-users')
                ->modifyQueryUsing(fn (Builder $query) => $query->involvingApprover()),
        ];

        // "All" only makes sense for oversight roles; everyone else is already
        // limited to their own contracts by the resource query, so the tab
        // would just duplicate "My contracts".
        if (auth()->user()?->hasAnyRole(Contract::OVERSIGHT_ROLES)) {
            $tabs['all'] = Tab::make(__('app.tab.all_contracts'))
                ->icon('heroicon-o-rectangle-stack');
        }

        return $tabs;
    }

    /**
     * Land each user on the tab that is actually useful to them instead of a
     * fixed first tab that is often empty: their approval queue if anything is
     * waiting, the full list for oversight roles, otherwise their own contracts.
     */
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
