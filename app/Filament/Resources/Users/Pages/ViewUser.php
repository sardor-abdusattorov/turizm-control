<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

/**
 * A full page for reading a user instead of the table's modal. The resource
 * defines no infolist on purpose, so Filament renders the resource form with
 * every field disabled — the same layout the edit page uses, read-only.
 */
class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
