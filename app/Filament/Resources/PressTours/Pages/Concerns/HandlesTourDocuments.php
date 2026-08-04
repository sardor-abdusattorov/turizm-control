<?php

namespace App\Filament\Resources\PressTours\Pages\Concerns;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Shared by CreatePressTour and EditPressTour. The report pack a finished
 * tour leaves behind is uploaded on the form, so both pages lift the virtual
 * upload fields out of the payload before save and file them as
 * PressTourAttachment records afterwards. Mirrors HandlesDossierUploads on
 * the contract pages.
 */
trait HandlesTourDocuments
{
    /** @var array<int|string, string> */
    protected array $documentFiles = [];

    /** @var array<int|string, string> */
    protected array $documentNames = [];

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function extractDocumentUploads(array $data): array
    {
        $this->documentFiles = (array) ($data['document_files'] ?? []);
        $this->documentNames = (array) ($data['document_names'] ?? []);
        unset($data['document_files'], $data['document_names']);

        return $data;
    }

    /**
     * Sync the pack to the upload field's state: a stored path missing from
     * the submitted list means its chip was removed, a submitted path with no
     * record is a fresh upload. A record whose file vanished from disk is
     * left alone — FileUpload drops those chips by itself, and that must not
     * read as a deliberate removal.
     */
    protected function storeTourDocuments(): void
    {
        $submitted = array_map(strval(...), array_values($this->documentFiles));
        $existing = $this->record->attachments()->get();

        $existing
            ->reject(fn ($attachment) => in_array($attachment->file_path, $submitted, true))
            ->filter(fn ($attachment) => Storage::disk('local')->exists($attachment->file_path))
            ->each(fn ($attachment) => $attachment->delete());

        $known = $existing->pluck('file_path')->all();
        $sort = (int) $this->record->attachments()->max('sort');

        foreach ($this->documentFiles as $key => $path) {
            if (in_array((string) $path, $known, true)) {
                continue;
            }

            $this->record->attachments()->create([
                // Filament keys the stored names by the stored PATH, not by
                // the upload uuid.
                'original_name' => $this->documentNames[$path] ?? $this->documentNames[$key] ?? basename((string) $path),
                'file_path' => $path,
                'size' => Storage::disk('local')->exists($path) ? Storage::disk('local')->size($path) : 0,
                'uploaded_by' => Auth::id(),
                'sort' => ++$sort,
            ]);
        }
    }
}
