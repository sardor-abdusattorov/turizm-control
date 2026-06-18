<?php

namespace App\Filament\Support;

use Filament\Forms\Components\FileUpload;

class DocumentUpload
{
    public static function make(string $folder, string $field = 'file'): FileUpload
    {
        return FileUpload::make($field)
            ->label(__('app.label.document'))
            // Private disk: documents are sensitive and must not be reachable by
            // a bare /storage URL. The local disk serves them only through
            // signed, expiring URLs (and our authenticated controllers).
            ->disk('local')
            ->directory(fn () => "uploads/files/{$folder}/".now()->format('Y/m'))
            ->visibility('private')
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
