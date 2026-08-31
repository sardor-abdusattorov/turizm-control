<?php

namespace App\Services\Telegram;

use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;

/**
 * The project register as the bot browses it: active projects, the ones
 * running or coming up first, so a phone-sized list leads with what matters.
 */
class BotProjectQueries
{
    /**
     * @return Builder<Project>
     */
    public function active(): Builder
    {
        return Project::query()
            ->active()
            ->orderByRaw('ends_on is null, ends_on < ?', [now()->toDateString()])
            ->orderBy('starts_on')
            ->orderByDesc('id');
    }
}
