<?php

namespace App\Filament\Resources\Orders\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

abstract class BaseEditOrder extends EditRecord
{
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
