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

    protected ?string $attachmentType = null;

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
        $this->attachmentType = $data['attachment_type'] ?? null;
        unset($data['attachment_files'], $data['attachment_names'], $data['attachment_type']);

        return $data;
    }

    protected function storeFormAttachments(): void
    {
        $sort = (int) $this->record->attachments()->max('sort');

        foreach ($this->attachmentFiles as $key => $path) {
            $this->record->attachments()->create([
                'type' => $this->attachmentType,
                'file_path' => $path,
                'original_name' => $this->attachmentNames[$key] ?? basename((string) $path),
                'size' => Storage::disk('local')->exists($path) ? Storage::disk('local')->size($path) : 0,
                'uploaded_by' => Auth::id(),
                'sort' => ++$sort,
            ]);
        }
    }
}
