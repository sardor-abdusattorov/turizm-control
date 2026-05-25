<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

final class ProjectUpdate extends Command
{

    protected $signature = 'project:update';

    protected $description = 'Project Update';

    public function handle(): void
    {
        $this->call('migrate');
        $this->call('shield:generate', [
            '--all' => true,
            '--panel' => 'admin',
            '--option' => 'policies_and_permissions',
            '--ignore-existing-policies' => true,
        ]);
        $this->call('filament:optimize-clear');
        $this->call('optimize:clear');
    }
}
