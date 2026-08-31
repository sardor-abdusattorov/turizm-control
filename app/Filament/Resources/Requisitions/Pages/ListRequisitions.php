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

    protected ?int $awaitingMe = null;

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

    /** Whatever is actually waiting on the viewer leads, when there is any. */
    public function getDefaultActiveTab(): string|int|null
    {
        return $this->awaitingMeCount() > 0 ? 'awaiting_me' : 'all';
    }

    protected function awaitingMeCount(): int
    {
        return $this->awaitingMe ??= static::getResource()::getEloquentQuery()->awaiting()->count();
    }
}
