<?php

namespace App\Filament\Support;

use Filament\Forms\Components\FileUpload;

class ImageUpload
{
    public static function make(string $folder, string $field = 'image'): FileUpload
    {
        return FileUpload::make($field)
            ->label(__('app.label.image'))
            // Private disk: uploads must never be reachable by a bare /storage
            // URL. The local disk has `serve => true`, so Filament (and our
            // models) serve these through short-lived signed temporary URLs.
            ->disk('local')
            ->directory(fn () => "uploads/images/{$folder}/".now()->format('Y/m'))
            ->visibility('private')
            ->image()
            ->imageEditor()
            ->previewable()
            ->downloadable()
            ->maxSize(6144);
    }
}
