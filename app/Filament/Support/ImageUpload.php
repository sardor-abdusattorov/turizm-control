<?php

namespace App\Filament\Support;

use Filament\Forms\Components\FileUpload;

class ImageUpload
{

    public static function make(string $folder, string $field = 'image'): FileUpload
    {
        return FileUpload::make($field)
            ->label(__('app.label.image'))
            ->disk('public')
            ->directory(fn () => "uploads/images/{$folder}/".now()->format('Y/m'))
            ->visibility('public')
            ->image()
            ->imageEditor()
            ->previewable()
            ->downloadable()
            ->maxSize(6144);
    }
}
