<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Support\Bytes;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

abstract class BaseViewOrder extends ViewRecord
{
    protected string $view = 'filament.resources.orders.pages.view-order';

    public function getHeading(): string
    {
        return $this->record->number ?: (string) $this->record->title;
    }

    public function getSubheading(): ?string
    {
        return null;
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

    public function fileSizeLabel(): ?string
    {
        if (! $this->record->fileExists()) {
            return null;
        }

        $bytes = filesize($this->record->fileAbsolutePath());

        if ($bytes === false) {
            return null;
        }

        return Bytes::human($bytes);
    }

    public function fileInlineUrl(): ?string
    {
        if (! $this->record->fileExists()) {
            return null;
        }

        return $this->record->isOpenableInOnlyOffice()
            ? route('orders.editor', ['order' => $this->record, 'mode' => 'view'])
            : route('orders.file.inline', ['order' => $this->record]);
    }

    public function fileEditorUrl(): ?string
    {
        return $this->record->isOpenableInOnlyOffice()
            ? route('orders.editor', ['order' => $this->record, 'mode' => 'edit'])
            : null;
    }
}
