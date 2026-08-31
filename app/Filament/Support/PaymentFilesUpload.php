<?php

namespace App\Filament\Support;

use App\Models\Payment;
use Filament\Forms\Components\FileUpload;

class PaymentFilesUpload
{
    public static function make(string $field = 'screenshots'): FileUpload
    {
        return FileUpload::make($field)
            ->label(__('app.label.screenshot'))
            ->disk('local')
            ->directory(fn () => 'uploads/images/'.Payment::SCREENSHOT_DIR.'/'.now()->format('Y/m'))
            ->visibility('private')
            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])
            ->multiple()
            ->reorderable()
            ->appendFiles()
            ->maxFiles(10)
            ->maxSize(10240)
            ->required()
            ->previewable()
            ->downloadable()
            ->panelLayout('grid');
    }
}
