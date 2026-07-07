<?php

namespace App\Filament\Widgets\Dashboard;

use App\Filament\Support\WidgetPermission;
use App\Models\Project;
use Filament\Widgets\Widget;

/**
 * The first thing the director sees: ONE concrete project with its live
 * numbers — the exhibition running right now, else the nearest upcoming one,
 * else the most recent past one. Not another counter row: the project card.
 */
class FeaturedProjectWidget extends Widget
{
    protected string $view = 'filament.widgets.dashboard.featured-project';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = -10;

    public static function canView(): bool
    {
        return WidgetPermission::allows(static::class)
            && Project::query()->where('status', true)->exists();
    }

    public function featuredProject(): ?Project
    {
        $base = fn () => Project::query()
            ->where('status', true)
            ->whereNotNull('starts_on')
            ->with(['participants.currency'])
            // Contract count honours the viewer's contract visibility.
            ->withCount(['contracts' => fn ($query) => $query->visibleTo()]);

        // Running now → soonest upcoming → most recently finished.
        return $base()
            ->whereDate('starts_on', '<=', today())
            ->whereDate('ends_on', '>=', today())
            ->orderBy('starts_on')
            ->first()
            ?? $base()->whereDate('starts_on', '>', today())->orderBy('starts_on')->first()
            ?? $base()->orderByDesc('starts_on')->first();
    }
}
