<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Contracts\ContractResource;
use App\Services\Dashboard\DashboardContext;
use Filament\Actions\Action;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    /**
     * The greeting becomes the page title — a native heading instead of a
     * bespoke widget, and the contextual line sits beneath it as the
     * subheading. "New contract" lives in the page header actions for authors,
     * so it never crowds the greeting.
     */
    public function getHeading(): string
    {
        return app(DashboardContext::class)->greetingHeading();
    }

    public function getSubheading(): ?string
    {
        return app(DashboardContext::class)->summaryLine();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createContract')
                ->label(__('app.action.create_contract'))
                ->icon('heroicon-m-plus')
                ->url(ContractResource::getUrl('create'))
                ->visible(fn (): bool => (bool) auth()->user()?->can('create_contract')),
        ];
    }
}
