<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Shared OnlyOffice document-key handling. The key identifies an editing
 * session; rotating it invalidates the cached document in OnlyOffice.
 */
trait HasDocumentKey
{
    public static function generateDocumentKey(): string
    {
        return Str::random(20);
    }

    public function refreshDocumentKey(): void
    {
        $this->update(['document_key' => static::generateDocumentKey()]);
    }
}
