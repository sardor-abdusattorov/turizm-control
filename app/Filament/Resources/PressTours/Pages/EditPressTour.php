<?php

namespace App\Filament\Resources\PressTours\Pages;

use App\Filament\Resources\PressTours\PressTourResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPressTour extends EditRecord
{
    protected static string $resource = PressTourResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return PressTourResource::getUrl('view', ['record' => $this->record]);
    }
}
