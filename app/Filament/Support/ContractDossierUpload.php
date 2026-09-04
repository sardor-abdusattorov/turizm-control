<?php

namespace App\Filament\Support;

use Filament\Forms\Components\FileUpload;

class ContractDossierUpload
{
    public static function make(string $field = 'attachment_files'): FileUpload
    {
        return FileUpload::make($field)
            ->label(__('app.label.attachments'))
            ->disk('local')

            ->directory(fn (): string => 'uploads/files/contract-attachments/'.now()->format('Y/m'))
            ->visibility('private')
            ->acceptedFileTypes([
                'application/pdf',
                'image/jpeg',
                'image/png',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ])
            ->multiple()

            ->appendFiles()
            ->maxFiles(40)
            ->maxSize(25600)
            ->storeFileNamesIn(self::namesField($field))
            ->openable()
            ->downloadable()
            ->previewable()
            ->imagePreviewHeight('120');
    }

    public static function namesField(string $field = 'attachment_files'): string
    {
        return $field === 'attachment_files' ? 'attachment_names' : $field.'_names';
    }
}
