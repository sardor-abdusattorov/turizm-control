<?php

namespace App\Filament\Widgets\Dashboard;

use App\Services\Telegram\TelegramService;
use Filament\Widgets\Widget;

class TelegramConnectWidget extends Widget
{
    protected string $view = 'filament.widgets.dashboard.telegram-connect';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = -20;

    protected static bool $isLazy = false;

    /**
     * Offer the prompt only on the dashboard, only while the bot is configured
     * and the account is not yet linked. The user menu keeps a permanent entry
     * for the rest of the panel.
     */
    public static function canView(): bool
    {
        $user = auth()->user();

        return $user !== null
            && app(TelegramService::class)->isConfigured()
            && ! $user->isTelegramLinked();
    }

    public function connectUrl(): string
    {
        return route('telegram.connect');
    }
}
