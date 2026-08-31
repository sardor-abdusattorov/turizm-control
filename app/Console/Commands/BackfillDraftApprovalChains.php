<?php

namespace App\Console\Commands;

use App\Models\Contract;
use App\Services\Contracts\ApprovalChain;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('contracts:backfill-draft-chains {--dry-run : Show which drafts would be filled without writing}')]
#[Description('Queue the default approval chain on draft contracts that have none')]
class BackfillDraftApprovalChains extends Command
{
    public function handle(ApprovalChain $chain): int
    {
        $drafts = Contract::query()
            ->where('status', Contract::STATUS_DRAFT)
            ->whereDoesntHave('approvers')
            ->with('responsible')
            ->get();

        if ($drafts->isEmpty()) {
            $this->info('No draft contracts need an approval chain.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $filled = 0;
        $skipped = 0;

        foreach ($drafts as $draft) {
            $userIds = $draft->responsible
                ? $chain->defaultUserIdsFor($draft->responsible)
                : [];

            if ($userIds === []) {
                $this->warn("  {$draft->number}: no default recipients or department flow — skipped");
                $skipped++;

                continue;
            }

            if ($dryRun) {
                $this->line("  {$draft->number}: would queue ".count($userIds).' approver(s)');

                continue;
            }

            $chain->requeue($draft, $userIds);
            $this->line("  {$draft->number}: queued ".count($userIds).' approver(s)');
            $filled++;
        }

        $this->newLine();

        if ($dryRun) {
            $this->info("Dry run — {$drafts->count()} chainless draft(s), {$skipped} would be skipped.");

            return self::SUCCESS;
        }

        $this->info("Filled {$filled} draft(s); skipped {$skipped}.");

        return self::SUCCESS;
    }
}
