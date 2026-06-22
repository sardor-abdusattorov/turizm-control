<?php

namespace App\Filament\Resources\TelegramMessageLogs\Pages;

use App\Filament\Resources\TelegramMessageLogs\TelegramMessageLogResource;
use Filament\Resources\Pages\ListRecords;

class ListTelegramMessageLogs extends ListRecords
{
    protected static string $resource = TelegramMessageLogResource::class;
}
