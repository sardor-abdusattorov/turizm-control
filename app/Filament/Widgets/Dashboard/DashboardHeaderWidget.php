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
     * picked by what is actually demanding the user's attention. The tone
     * drives the soft glow on the card's left edge.
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

        [$summary, $tone] = match (true) {
            $overdue > 0 => [__('app.dashboard.summary_overdue', ['count' => $overdue, 'total' => $awaiting]), 'danger'],
            $awaiting > 0 => [__('app.dashboard.summary_awaiting', ['count' => $awaiting]), 'warning'],
            $stalled > 0 => [__('app.dashboard.summary_stalled', ['count' => $stalled]), 'warning'],
            default => [__('app.dashboard.summary_clear'), 'success'],
        };

        return ['greeting' => $greeting, 'summary' => $summary, 'tone' => $tone];
    }

    /**
     * The user's own photo for the greeting disc, when one is uploaded —
     * the same source as the panel's top-bar avatar. Null falls back to
     * the CSS initials disc.
     */
    public function userAvatarUrl(): ?string
    {
        return auth()->user()?->getFilamentAvatarUrl();
    }

    /**
     * Initials for the local avatar disc — first letters of the first two
     * name words. Rendered in CSS, so no external avatar service is needed.
     */
    public function userInitials(): string
    {
        $words = preg_split('/\s+/', trim((string) auth()->user()?->name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $letters = array_map(
            fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1)),
            array_slice($words, 0, 2),
        );

        return implode('', $letters) !== '' ? implode('', $letters) : '·';
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
     * Whether to surface the "connect Telegram" prompt inside the greeting.
     * It sits where the user starts their day; profile settings keep the
     * permanent connect/disconnect actions.
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
