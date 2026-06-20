<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

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
