<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

/**
 * The dashboard is just a widget stack: the greeting card first, then the
 * project pulse. The project picker lives INSIDE the pulse widget (not in a
 * page filters form) — Filament renders filtersForm above every widget, which
 * would push a bare select over the greeting.
 */
class Dashboard extends BaseDashboard
{
    /**
     * The header card and the project pulse are both full-width, so a single
     * column is all the grid the page needs.
     */
    public function getColumns(): int|array
    {
        return 1;
    }
}
