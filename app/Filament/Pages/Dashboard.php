<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

/**
 * The greeting and contextual line live in the DashboardHeaderWidget card —
 * together with the Telegram connect prompt — so the page itself keeps the
 * plain Filament header.
 */
class Dashboard extends BaseDashboard
{
    /**
     * Two columns on wide screens so the short, always-paired finance tables
     * (outstanding + latest payments) can sit side by side; every other widget
     * spans the full width. A single column on phones and tablets.
     */
    public function getColumns(): int|array
    {
        return ['default' => 1, 'xl' => 2];
    }
}
