<?php

namespace App\Filament\Support;

use Filament\Forms\Components\FileUpload;

class ContractDossierUpload
{
    /**
     * The contract dossier: signed scan, buyruq copy, competitors' proposals,
     * invoice, SWIFT slip, act, bank-fee statement. Shared by the contract
     * form and the view page's Attachments panel so both offer the same
     * grid, the same limits and the same storage layout.
     */
    public static function make(string $field = 'attachment_files'): FileUpload
    {
        return FileUpload::make($field)
            ->label(__('app.label.attachments'))
            ->helperText(__('app.helper.attachment_scans'))
            ->disk('local')
            // Same private, month-bucketed layout as DocumentUpload /
            // ImageUpload use everywhere.
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

    /**
     * Filament keys the uploaded names by their stored PATH, in a sibling
     * state key — both writers read it back under this name.
     */
    public static function namesField(string $field = 'attachment_files'): string
    {
        return $field === 'attachment_files' ? 'attachment_names' : $field.'_names';
    }
}
