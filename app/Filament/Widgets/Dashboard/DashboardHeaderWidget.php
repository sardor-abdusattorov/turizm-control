<?php

namespace App\Filament\Widgets\Dashboard;

use App\Filament\Resources\Contracts\ContractResource;
use App\Services\Dashboard\DashboardContext;
use Filament\Widgets\Widget;

class DashboardHeaderWidget extends Widget
{
    protected string $view = 'filament.widgets.dashboard.header';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = -30;

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

    public function canCreateContract(): bool
    {
        return (bool) auth()->user()?->can('create_contract');
    }

    public function createContractUrl(): string
    {
        return ContractResource::getUrl('create');
    }
}
