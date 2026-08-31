<?php

namespace App\Filament\Support;

use Filament\Forms\Components\FileUpload;

class PressTourDocumentsUpload
{
    public static function make(string $field = 'document_files'): FileUpload
    {
        return FileUpload::make($field)
            ->label(__('app.label.press_tour_documents'))
            ->helperText(__('app.helper.press_tour_documents'))
            ->disk('local')
            ->directory(fn (): string => 'uploads/files/press-tours/'.now()->format('Y/m'))
            ->visibility('private')
            ->acceptedFileTypes([
                'application/pdf',
                'image/jpeg',
                'image/png',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->multiple()

            ->appendFiles()
            ->maxFiles(40)
            ->maxSize(25600)
            ->storeFileNamesIn(self::namesField($field))
            ->openable()
            ->downloadable()
            ->previewable()
            ->panelLayout('grid');
    }

    public static function namesField(string $field = 'document_files'): string
    {
        return $field.'_names';
    }
}
