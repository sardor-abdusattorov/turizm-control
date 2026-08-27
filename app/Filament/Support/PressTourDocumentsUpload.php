<?php

namespace App\Filament\Support;

use Filament\Forms\Components\FileUpload;

class PressTourDocumentsUpload
{
    /**
     * The report pack a finished tour leaves behind: the report itself, media
     * coverage, photos, the programme, the participant list and the act.
     * Spreadsheets are accepted too — participant lists arrive as .xlsx.
     */
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
            // No ->reorderable(): the dossier is filed in arrival order and the
            // panel's array order is not a reliable statement of intent, so a
            // drag handle would promise an order that never sticks.
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
