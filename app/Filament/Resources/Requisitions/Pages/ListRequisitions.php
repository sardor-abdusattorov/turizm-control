<?php

namespace App\Filament\Resources\Requisitions\Pages;

use App\Enums\RequisitionStatus;
use App\Exports\RequisitionsExport;
use App\Filament\Resources\Requisitions\RequisitionResource;
use App\Filament\Support\ExportPermission;
use App\Filament\Support\ExportXlsxAction;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Facades\Excel;

class ListRequisitions extends ListRecords
{
    protected static string $resource = RequisitionResource::class;

    protected ?int $awaitingMe = null;

    protected function getHeaderActions(): array
    {
        return [
            ExportXlsxAction::make()
                ->visible(fn (): bool => ExportPermission::allows('export_requisition'))
                ->action(fn ($livewire) => Excel::download(
                    new RequisitionsExport($livewire->getFilteredTableQuery()),
                    'requisitions-'.now()->format('Y-m-d').'.xlsx',
                )),
            CreateAction::make(),
        ];
    }

    /** @return array<string, Tab> */
    public function getTabs(): array
    {
        $tabs = [
            'all' => Tab::make(__('app.label.all'))
                ->icon('heroicon-o-rectangle-stack'),

            'mine' => Tab::make(__('app.approval.filter.mine'))
                ->icon('heroicon-o-user')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('author_id', auth()->id())),

            'awaiting_me' => Tab::make(__('app.approval.filter.awaiting_me'))
                ->icon('heroicon-o-inbox-arrow-down')
                ->badge($this->awaitingMeCount() ?: null)
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->awaiting()),
        ];

        foreach (RequisitionStatus::cases() as $status) {
            $tabs[$status->value] = Tab::make($status->label())
                ->icon($status->icon())
                ->badgeColor($status->color())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', $status));
        }

        return $tabs;
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return $this->awaitingMeCount() > 0 ? 'awaiting_me' : 'all';
    }

    protected function awaitingMeCount(): int
    {
        return $this->awaitingMe ??= static::getResource()::getEloquentQuery()->awaiting()->count();
    }
}
