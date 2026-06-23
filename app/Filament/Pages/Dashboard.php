<?php

namespace App\Filament\Pages;

use App\Services\Dashboard\DashboardContext;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    /**
     * The greeting is Filament's own page heading, with the contextual line as
     * the subheading — no bespoke greeting card, just the native page header.
     */
    public function getHeading(): string
    {
        return app(DashboardContext::class)->greetingHeading();
    }

    public function getSubheading(): ?string
    {
        return app(DashboardContext::class)->summaryLine();
    }
}
