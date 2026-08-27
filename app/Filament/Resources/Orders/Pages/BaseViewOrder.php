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
            // Office files have no in-browser viewer any more, so the single
            // action just serves the file — the browser previews what it can
            // (PDF, images) and downloads the rest.
            Action::make('viewFile')
                ->label(__('app.action.open_file'))
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->url(fn () => route('orders.file.inline', ['order' => $this->record]), shouldOpenInNewTab: true)
                ->visible(fn () => $this->record?->fileExists()),

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

        return route('orders.file.inline', ['order' => $this->record]);
    }
}
