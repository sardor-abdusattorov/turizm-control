<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

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

        $this->call('migrate:fresh', [
            '--force' => true,
        ]);
        $this->call('shield:generate', [
            '--all' => true,
            '--panel' => 'admin',
            '--option' => 'policies_and_permissions',
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
}
