<?php

namespace App\Filament\Support;

use Filament\Forms\Components\FileUpload;

class MediaGalleryUpload
{
    /**
     * Photo + video gallery. Video uploads are also capped by the Livewire
     * temporary-upload rules (config/livewire.php) and the server's
     * upload_max_filesize / post_max_size.
     *
     * @var list<string>
     */
    public const ACCEPTED_MIMES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
        'image/avif',
        'video/mp4',
        'video/quicktime',
        'video/x-msvideo',
        'video/avi',
        'video/webm',
        'video/x-matroska',
    ];

    public static function make(string $folder, string $field = 'gallery'): FileUpload
    {
        return FileUpload::make($field)
            ->label(__('app.label.gallery'))
            ->disk('local')
            ->directory(fn () => "uploads/images/{$folder}/".now()->format('Y/m'))
            ->visibility('private')
            ->acceptedFileTypes(self::ACCEPTED_MIMES)
            ->multiple()
            ->reorderable()
            ->appendFiles()
            ->maxFiles(24)
            ->maxSize(102400)
            ->previewable()
            ->downloadable()
            ->panelLayout('grid');
    }
}
