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
        if ($this->getLaravel()->isProduction()) {
            $this->components->error('project:init wipes the database and loads demo data — it will not run in production. Use `php artisan migrate --force` instead.');

            return self::FAILURE;
        }

        $this->snapshotHandEnteredData();

        $this->call('migrate:fresh', [
            '--force' => true,
        ]);

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

    private function snapshotHandEnteredData(): void
    {
        if (! Schema::hasTable('contracts')) {
            return;
        }

        $this->call('contracts:snapshot');
    }
}
