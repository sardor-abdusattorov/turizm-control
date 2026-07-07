<?php

namespace App\Filament\Resources\Orders\Pages;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

abstract class BaseCreateOrder extends CreateRecord
{
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['scope'] = static::getResource()::orderScope()->value;
        $data['created_by'] = Auth::id();

        return $data;
    }
}
