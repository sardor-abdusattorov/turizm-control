<?php

namespace App\Filament\Support;

use Filament\Forms\Components\FileUpload;

class DocumentUpload
{
    public static function make(string $folder, string $field = 'file'): FileUpload
    {
        return FileUpload::make($field)
            ->label(__('app.label.document'))
            ->disk('public')
            ->directory(fn () => "uploads/files/{$folder}/" . now()->format('Y/m'))
            ->visibility('public')
            ->acceptedFileTypes([
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->downloadable()
            ->previewable(false)
            ->nullable();
    }
}
