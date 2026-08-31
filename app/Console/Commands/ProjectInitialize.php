<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

final class ProjectInitialize extends Command
{
    protected $signature = 'project:init';

    protected $description = 'Project Initialization';

    public function handle(): int
    {
        // project:init starts with migrate:fresh — it DROPS every table and
        // reloads demo data. That is a first-time/local setup, never something
        // to run against a live database. Refuse outright in production so a
        // misconfigured shell can't wipe real contracts. For routine updates
        // run `php artisan migrate --force` (see DEPLOY.md).
        if ($this->getLaravel()->isProduction()) {
            $this->components->error('project:init wipes the database and loads demo data — it will not run in production. Use `php artisan migrate --force` instead.');

            return self::FAILURE;
        }

        $this->snapshotHandEnteredData();

        $this->call('migrate:fresh', [
            '--force' => true,
        ]);
        // --ignore-existing-policies, as project:update already does: several
        // policies carry hand-written record-level rules on top of the
        // permission check (a requisition is its author's and its reviewer's),
        // and a regenerated stub would silently widen access.
        $this->call('shield:generate', [
            '--all' => true,
            '--panel' => 'admin',
            '--option' => 'policies_and_permissions',
            '--ignore-existing-policies' => true,
        ]);
        $this->call('db:seed', [
            '--force' => true,
        ]);
        $this->call('shield:super-admin', [
            '--user' => '1',
            '--panel' => 'admin',
        ]);

        $this->call('filament:optimize-clear');
        $this->call('optimize:clear');

        return self::SUCCESS;
    }

    /**
     * Write the snapshot HandEnteredContractsSeeder replays, before the drop.
     * Forgetting `contracts:snapshot` used to lose every record entered since
     * the last one — and silently, because the stale file still restored
     * something. Skipped on a first-ever run, where there is no table to read.
     */
    private function snapshotHandEnteredData(): void
    {
        if (! Schema::hasTable('contracts')) {
            return;
        }

        $this->call('contracts:snapshot');
    }
}
