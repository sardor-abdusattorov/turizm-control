<?php

namespace App\Filament\Resources\PressTours\Pages;

use App\Filament\Resources\PressTours\PressTourResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Livewire\Attributes\On;

class ViewPressTour extends ViewRecord
{
    protected static string $resource = PressTourResource::class;

    protected string $view = 'filament.resources.press-tours.pages.view-press-tour';

    public function getHeading(): string
    {
        return $this->record->name;
    }

    public function getSubheading(): ?string
    {
        return trim(implode(' · ', array_filter([
            $this->record->place,
            $this->record->period,
        ]))) ?: null;
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }

    #[On('attachments-saved')]
    public function refreshDocumentCount(): void {}
}
