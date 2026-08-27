<?php

namespace App\Filament\Resources\Contracts\Pages\Concerns;

use App\Services\Documents\SyncAttachments;

/**
 * Shared by CreateContract and EditContract: the dossier scans ride on the
 * form as a Filament FileUpload panel, so both pages pull the virtual upload
 * fields out of the payload before save and hand them to SyncAttachments —
 * the same syncer the view page's dossier panel uses.
 */
trait HandlesDossierUploads
{
    /** @var array<int|string, string> */
    protected array $attachmentFiles = [];

    /** @var array<int|string, string> */
    protected array $attachmentNames = [];

    /**
     * Pull the virtual upload fields out of the form payload. Keys are kept
     * as-is: original_name lookups match on the same keys.
     *
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

    /**
     * Sync the dossier to the upload panel's state: the submitted list is the
     * dossier, in order — a removed chip deletes its attachment (and file),
     * a new path is filed, and dragging chips around re-sorts them.
     */
    protected function storeFormAttachments(): void
    {
        app(SyncAttachments::class)->sync(
            $this->record->attachments(),
            $this->attachmentFiles,
            $this->attachmentNames,
        );
    }
}
