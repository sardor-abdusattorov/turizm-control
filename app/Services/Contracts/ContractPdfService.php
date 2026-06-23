<?php

namespace App\Services\Contracts;

use App\Models\Contract;
use App\Services\OnlyOffice\OnlyOfficeService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class ContractPdfService
{
    public function __construct(
        public OnlyOfficeService $onlyOffice,
        public ContractFiles $files,
    ) {}

    public function path(Contract $contract): string
    {
        return $this->files->pdfPath($contract);
    }

    public function absolutePath(Contract $contract): string
    {
        return Storage::disk('local')->path($this->path($contract));
    }

    public function exists(Contract $contract): bool
    {
        return Storage::disk('local')->exists($this->path($contract));
    }

    public function isUpToDate(Contract $contract): bool
    {
        $disk = Storage::disk('local');
        $pdfPath = $this->path($contract);
        $docxPath = $contract->documentPath();

        if (! $disk->exists($pdfPath) || ! $disk->exists($docxPath)) {
            return false;
        }

        return $disk->lastModified($pdfPath) >= $disk->lastModified($docxPath);
    }

    public function ensure(Contract $contract): ?string
    {
        if (! $contract->documentExists()) {
            return null;
        }

        if ($this->isUpToDate($contract)) {
            return $this->absolutePath($contract);
        }

        return $this->generate($contract);
    }

    public function generate(Contract $contract): ?string
    {
        if (! $contract->documentExists()) {
            return null;
        }

        $remoteUrl = $this->onlyOffice->convertToPdf($contract);

        if (! $remoteUrl) {
            return null;
        }

        $body = Http::timeout(60)
            ->get($this->onlyOffice->internalDownloadUrl($remoteUrl))
            ->body();

        if ($body === '') {
            return null;
        }

        Storage::disk('local')->put($this->path($contract), $body);

        return $this->absolutePath($contract);
    }
}
