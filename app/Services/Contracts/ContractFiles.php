<?php

namespace App\Services\Contracts;

use App\Models\Contract;
use App\Services\Documents\ContractPlaceholderValues;
use App\Services\Documents\TemplateFiller;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * Single owner of every on-disk path and file operation for a contract: the
 * working docx, the rendered PDF, the per-contract folder, generation from a
 * template, and cleanup. Keeps storage knowledge out of the Contract model so
 * a path can be changed in exactly one place.
 */
class ContractFiles
{
    public const DISK = 'local';

    public function directory(Contract $contract): string
    {
        return "uploads/files/contracts/{$contract->id}";
    }

    public function documentPath(Contract $contract): string
    {
        return $this->directory($contract).'/draft.docx';
    }

    public function documentAbsolutePath(Contract $contract): string
    {
        return $this->disk()->path($this->documentPath($contract));
    }

    public function documentExists(Contract $contract): bool
    {
        return $this->disk()->exists($this->documentPath($contract));
    }

    public function pdfPath(Contract $contract): string
    {
        return $this->directory($contract).'/contract.pdf';
    }

    /**
     * Render the contract's working docx from its template and point the
     * `document_file` column at it. No-op when the template or its file is
     * missing.
     */
    public function buildFromTemplate(Contract $contract, TemplateFiller $filler, ContractPlaceholderValues $values): void
    {
        $template = $contract->template;

        if (! $template || ! $template->template_file) {
            return;
        }

        $templateAbsolute = $this->disk()->path($template->template_file);

        if (! is_file($templateAbsolute)) {
            return;
        }

        $filler->fill($templateAbsolute, $this->documentAbsolutePath($contract), $values->for($contract));

        $contract->update([
            'document_file' => $this->documentPath($contract),
            'document_key' => Contract::generateDocumentKey(),
        ]);
    }

    /**
     * Remove every file the contract owns — the explicit document/pdf columns
     * plus the whole per-contract folder. Safe to call repeatedly.
     */
    public function purge(Contract $contract): void
    {
        foreach ([$contract->document_file, $contract->pdf_file] as $path) {
            if ($path && $this->disk()->exists($path)) {
                $this->disk()->delete($path);
            }
        }

        $folder = $this->directory($contract);

        if ($this->disk()->exists($folder)) {
            $this->disk()->deleteDirectory($folder);
        }
    }

    private function disk(): Filesystem
    {
        return Storage::disk(self::DISK);
    }
}
