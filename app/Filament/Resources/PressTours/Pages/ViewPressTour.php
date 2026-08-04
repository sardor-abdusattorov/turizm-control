<?php

namespace App\Filament\Resources\PressTours\Pages;

use App\Filament\Resources\PressTours\PressTourResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

/**
 * A designed read page rather than a greyed-out copy of the edit form: the
 * facts as a card, the report pack as a stock Filament table, both under
 * native tabs — the same shape the contract view uses. No infolist.
 */
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
}
