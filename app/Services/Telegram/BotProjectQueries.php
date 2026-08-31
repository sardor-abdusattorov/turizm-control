<?php

namespace App\Services\Telegram;

use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;

class BotProjectQueries
{
    /** @return Builder<Project> */
    public function active(): Builder
    {
        return Project::query()
            ->active()
            ->orderByRaw('ends_on is null, ends_on < ?', [now()->toDateString()])
            ->orderBy('starts_on')
            ->orderByDesc('id');
    }
}
