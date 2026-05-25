<?php

namespace App\Filament\Support;

use Filament\Forms\Components\RichEditor;

class TextEditor
{
    public static function make(string $folder, string $field): RichEditor
    {
        return RichEditor::make($field)
            ->fileAttachmentsDisk('public')
            ->fileAttachmentsDirectory(fn () => "uploads/attachments/{$folder}/".now()->format('Y/m'))
            ->fileAttachmentsVisibility('public')
            ->columnSpanFull()
            ->toolbarButtons([
                ['bold', 'italic', 'underline', 'strike', 'subscript', 'superscript', 'link'],
                ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'paragraph'],
                ['alignStart', 'alignCenter', 'alignEnd', 'alignJustify'],
                ['blockquote', 'codeBlock', 'bulletList', 'orderedList'],
                ['highlight', 'textColor', 'clearFormatting'],
                ['details', 'horizontalRule', 'lead', 'small', 'code'],
                ['table', 'attachFiles'],
                ['grid'],
                ['undo', 'redo'],
                ['fullscreen'],
            ]);
    }
}
