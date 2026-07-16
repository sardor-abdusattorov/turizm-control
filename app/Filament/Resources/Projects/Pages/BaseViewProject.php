<?php

namespace App\Filament\Resources\Projects\Pages;

use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

abstract class BaseViewProject extends ViewRecord
{
    protected string $view = 'filament.resources.projects.pages.view-project';

    public function getHeading(): string
    {
        return (string) $this->record->name;
    }

    public function getSubheading(): ?string
    {
        return null;
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
