<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

final class Recache extends Command
{

    protected $signature = 'project:cache';

    protected $description = 'Project Cache Refresh';

    public function handle(): void
    {
        $this->call('filament:optimize-clear');
        $this->call('optimize:clear');
        $this->call('optimize');
        $this->call('filament:optimize');
    }
}
