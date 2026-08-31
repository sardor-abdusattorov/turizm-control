<?php

namespace App\Services\Contracts;

use App\Models\Contract;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

class ContractFiles
{
    public const DISK = 'local';

    public function directory(Contract $contract): string
    {
        return "uploads/files/contracts/{$contract->id}";
    }

    public function purge(Contract $contract): void
    {
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
