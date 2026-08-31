<?php

namespace App\Filament\Resources\Contracts\Pages\Concerns;

use App\Services\Documents\SyncAttachments;

trait HandlesDossierUploads
{
    /** @var array<int|string, string> */
    protected array $attachmentFiles = [];

    /** @var array<int|string, string> */
    protected array $attachmentNames = [];

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function extractAttachmentUploads(array $data): array
    {
        $this->attachmentFiles = (array) ($data['attachment_files'] ?? []);
        $this->attachmentNames = (array) ($data['attachment_names'] ?? []);
        unset($data['attachment_files'], $data['attachment_names']);

        return $data;
    }

    protected function storeFormAttachments(): void
    {
        app(SyncAttachments::class)->sync(
            $this->record->attachments(),
            $this->attachmentFiles,
            $this->attachmentNames,
        );
    }
}
