<?php

namespace App\Filament\Resources\Contracts\Pages\Concerns;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Shared by CreateContract and EditContract: the dossier scans are uploaded
 * on the form (never on the view page), so both pages pull the virtual
 * attachment fields out of the payload before save and file them as
 * ContractAttachment records after.
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
     * Sync the dossier to the upload field's state. The edit form prefills
     * the field with the stored files, so: a path missing from the submitted
     * list means its chip was removed — the attachment goes (the model hook
     * deletes the file too); a path without a record is a fresh upload.
     */
    protected function storeFormAttachments(): void
    {
        $submitted = array_map(strval(...), array_values($this->attachmentFiles));
        $existing = $this->record->attachments()->get();

        // Guard: FileUpload silently drops chips whose file is missing on
        // disk, so a record with a lost file must not be mistaken for a
        // deliberate removal.
        $existing
            ->reject(fn ($attachment) => in_array($attachment->file_path, $submitted, true))
            ->filter(fn ($attachment) => Storage::disk('local')->exists($attachment->file_path))
            ->each(fn ($attachment) => $attachment->delete());

        $known = $existing->pluck('file_path')->all();
        $sort = (int) $this->record->attachments()->max('sort');

        foreach ($this->attachmentFiles as $key => $path) {
            if (in_array((string) $path, $known, true)) {
                continue;
            }

            $this->record->attachments()->create([
                'file_path' => $path,
                // Filament keys the stored names by the stored PATH, not by
                // the upload uuid — looking up by $key alone always missed.
                'original_name' => $this->attachmentNames[$path] ?? $this->attachmentNames[$key] ?? basename((string) $path),
                'size' => Storage::disk('local')->exists($path) ? Storage::disk('local')->size($path) : 0,
                'uploaded_by' => Auth::id(),
                'sort' => ++$sort,
            ]);
        }
    }
}
