<?php

namespace App\Filament\Resources\PressTours\Pages;

use App\Filament\Resources\PressTours\PressTourResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

/**
 * No infolist on purpose — Filament falls back to the resource form with its
 * fields disabled, which keeps one layout for reading and editing a tour.
 */
class ViewPressTour extends ViewRecord
{
    protected static string $resource = PressTourResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
