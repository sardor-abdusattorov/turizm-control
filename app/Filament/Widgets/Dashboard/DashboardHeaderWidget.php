<?php

namespace App\Filament\Widgets\Dashboard;

use App\Filament\Resources\Contracts\ContractResource;
use App\Services\Dashboard\DashboardContext;
use App\Services\Telegram\TelegramService;
use Filament\Widgets\Widget;

class DashboardHeaderWidget extends Widget
{
    protected string $view = 'filament.widgets.dashboard.header';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = -20;

    protected static bool $isLazy = false;

    /**
     * One personalised line that answers "what should I care about right now",
     * picked by what is actually demanding the user's attention.
     *
     * @return array{greeting: string, summary: string, tone: string}
     */
    public function headerData(): array
    {
        $context = app(DashboardContext::class);
        $name = $context->firstName();

        $greeting = $name !== ''
            ? __('app.dashboard.greeting_named', ['name' => $name])
            : __('app.dashboard.greeting');

        $overdue = $context->isApprover() ? $context->overdueForMe()->count() : 0;
        $awaiting = $context->isApprover() ? $context->awaitingMe()->count() : 0;
        $stalled = $context->isManager() ? $context->managerCounts()['stalled'] : 0;

        if ($overdue > 0) {
            return [
                'greeting' => $greeting,
                'summary' => __('app.dashboard.summary_overdue', ['count' => $overdue, 'total' => $awaiting]),
                'tone' => 'danger',
            ];
        }

        if ($awaiting > 0) {
            return [
                'greeting' => $greeting,
                'summary' => __('app.dashboard.summary_awaiting', ['count' => $awaiting]),
                'tone' => 'warning',
            ];
        }

        if ($stalled > 0) {
            return [
                'greeting' => $greeting,
                'summary' => __('app.dashboard.summary_stalled', ['count' => $stalled]),
                'tone' => 'warning',
            ];
        }

        return [
            'greeting' => $greeting,
            'summary' => __('app.dashboard.summary_clear'),
            'tone' => 'success',
        ];
    }

    /**
     * Clickable "needs me" counters — each is one tap away from the exact
     * contracts list tab it counts. Only non-zero chips render, so a clear
     * day keeps the header to a single calm line.
     *
     * @return array<int, array{label: string, count: int, tone: string, url: string}>
     */
    public function attentionChips(): array
    {
        $context = app(DashboardContext::class);
        $chips = [];

        if ($context->isApprover()) {
            $awaitingUrl = ContractResource::getUrl('index', ['activeTab' => 'awaiting_me']);
            $overdue = $context->overdueForMe()->count();
            $awaiting = $context->awaitingMe()->count();

            if ($overdue > 0) {
                $chips[] = ['label' => __('app.dashboard.chip_overdue'), 'count' => $overdue, 'tone' => 'danger', 'url' => $awaitingUrl];
            }

            if ($awaiting > 0) {
                $chips[] = ['label' => __('app.dashboard.chip_awaiting'), 'count' => $awaiting, 'tone' => 'warning', 'url' => $awaitingUrl];
            }
        }

        if ($context->isManager() && $context->managerCounts()['stalled'] > 0) {
            $chips[] = [
                'label' => __('app.dashboard.chip_stalled'),
                'count' => $context->managerCounts()['stalled'],
                'tone' => 'gray',
                'url' => ContractResource::getUrl('index', ['activeTab' => 'my_contracts']),
            ];
        }

        return $chips;
    }

    /**
     * The "create contract" quick action for users who may author contracts —
     * the dashboard is otherwise read-only, and a manager lands here to start
     * work. Null hides the button for everyone else.
     */
    public function createContractUrl(): ?string
    {
        return auth()->user()?->can('create_contract')
            ? ContractResource::getUrl('create')
            : null;
    }

    /**
     * Whether to surface the "connect Telegram" prompt inside the header card.
     * Lives here — on the dashboard only — instead of a panel-wide render hook,
     * so the reminder shows once where the user lands, not on every page. The
     * user menu keeps a permanent "Connect Telegram" item for the other pages.
     */
    public function shouldOfferTelegram(): bool
    {
        $user = auth()->user();

        if ($user === null || ! app(TelegramService::class)->isConfigured()) {
            return false;
        }

        // A link whose owner blocked the bot is dead — offer to reconnect.
        return ! $user->isTelegramLinked()
            || $user->telegram?->blocked_at !== null;
    }
}
