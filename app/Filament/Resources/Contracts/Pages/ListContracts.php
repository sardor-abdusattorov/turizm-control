<?php

namespace App\Filament\Resources\Contracts\Pages;

use App\Exports\ContractsExport;
use App\Filament\Resources\Contracts\ContractResource;
use App\Filament\Support\ExportXlsxAction;
use App\Models\Contract;
use App\Models\ContractApprover;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Facades\Excel;

class ListContracts extends ListRecords
{
    protected static string $resource = ContractResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ExportXlsxAction::make()
                ->visible(fn (): bool => ViewContract::userCanExportContract())
                ->action(fn ($livewire) => Excel::download(
                    new ContractsExport($livewire->getFilteredTableQuery()),
                    'contracts-'.now()->format('Y-m-d').'.xlsx',
                )),
            CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        $user = auth()->user();

        if (! $user) {
            return [];
        }

        $isAuthor = $user->can('create_contract')
            || Contract::query()->where('responsible_id', $user->id)->exists();
        $isApprover = ContractApprover::query()->where('user_id', $user->id)->exists();

        $canViewAll = $user->hasAnyRole(Contract::OVERSIGHT_ROLES)
            || $user->can('view_all_contracts');

        $tabs = [];

        if ($canViewAll) {
            $tabs['all'] = Tab::make(__('app.tab.all_contracts'))
                ->icon('heroicon-o-rectangle-stack');
        }

        if ($isAuthor) {
            $tabs['my_contracts'] = Tab::make(__('app.tab.my_contracts'))
                ->icon('heroicon-o-user')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('responsible_id', $user->id));
        }

        if ($isApprover) {
            $awaitingCount = Contract::query()->awaitingApprovalBy()->count();

            $tabs['awaiting_me'] = Tab::make(__('app.tab.awaiting_me'))
                ->icon('heroicon-o-inbox-arrow-down')
                ->modifyQueryUsing(fn (Builder $query) => $query->awaitingApprovalBy())
                ->badge($awaitingCount > 0 ? $awaitingCount : null)
                ->badgeColor('warning');

            $tabs['involving_me'] = Tab::make(__('app.tab.involving_me'))
                ->icon('heroicon-o-users')
                ->modifyQueryUsing(fn (Builder $query) => $query->involvingApprover());
        }

        if (array_keys($tabs) === ['my_contracts']) {
            return [];
        }

        return $tabs;
    }

    public function getDefaultActiveTab(): string|int|null
    {
        $user = auth()->user();

        if ($user && Contract::query()->awaitingApprovalBy($user)->exists()) {
            return 'awaiting_me';
        }

        $tabs = $this->getTabs();

        return array_key_first($tabs);
    }
}
