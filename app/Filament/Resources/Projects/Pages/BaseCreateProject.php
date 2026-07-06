<?php

namespace App\Filament\Resources\Projects\Pages;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

abstract class BaseCreateProject extends CreateRecord
{
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['type'] = static::getResource()::projectType()->value;
        $data['created_by'] = Auth::id();

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
