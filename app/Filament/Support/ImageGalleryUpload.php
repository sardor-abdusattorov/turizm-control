<?php

namespace App\Filament\Support;

use Filament\Forms\Components\FileUpload;

class ImageGalleryUpload
{
    public static function make(string $folder, string $field = 'gallery'): FileUpload
    {
        return FileUpload::make($field)
            ->label(__('app.label.gallery'))
            ->disk('local')
            ->directory(fn () => "uploads/images/{$folder}/".now()->format('Y/m'))
            ->visibility('private')
            ->image()
            ->multiple()
            ->reorderable()
            ->appendFiles()
            ->maxFiles(24)
            ->maxSize(6144)
            ->previewable()
            ->downloadable()
            ->panelLayout('grid');
    }
}
