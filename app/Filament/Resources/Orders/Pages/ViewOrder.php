<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Orders\Schemas\OrderInfolist;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    public function infolist(Schema $schema): Schema
    {
        return OrderInfolist::configure($schema);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('openEditor')
                ->label(__('app.action.open_editor'))
                ->icon('heroicon-o-pencil-square')
                ->color('primary')
                ->url(fn () => route('orders.editor', ['order' => $this->record, 'mode' => 'edit']))
                ->visible(fn () => $this->record?->fileExists() && $this->record?->isOpenableInOnlyOffice()),

            Action::make('viewFile')
                ->label(__('app.action.open_file'))
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->url(fn () => route('orders.file.inline', ['order' => $this->record]), shouldOpenInNewTab: true)
                ->visible(fn () => $this->record?->fileExists() && ! $this->record?->isOpenableInOnlyOffice()),

            EditAction::make(),
        ];
    }
}
