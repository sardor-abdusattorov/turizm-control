<?php

namespace App\Observers;

use App\Models\Contract;
use App\Services\Contracts\ContractFiles;

class ContractObserver
{
    public function __construct(private ContractFiles $files) {}

    public function updating(Contract $contract): void
    {
        $contract->maybeInvalidateOnEdit();
    }

    public function deleting(Contract $contract): void
    {
        $this->files->purge($contract);

        $contract->attachments()->get()->each->delete();
        $contract->payments()->get()->each->delete();
    }
}
