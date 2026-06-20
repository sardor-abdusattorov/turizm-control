<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Contracts\ContractResource;
use App\Models\Contract;
use App\Models\ContractApprover;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ContractStatsWidget extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        // The widget is built around "my contracts" / "awaiting me" — that only
        // makes sense for users who actually own a queue. Pure accountant gets
        // their own PaymentStatsWidget instead.
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $user->hasAnyRole(['super_admin', 'manager', 'director', 'legal_officer'])
            || $user->hasAnyRole(Contract::OVERSIGHT_ROLES);
    }

    protected function getStats(): array
    {
        $user = auth()->user();
        $userId = $user?->id;

        $listUrl = ContractResource::getUrl('index');

        $awaiting = Contract::query()->awaitingApprovalBy()->count();

        $overdue = ContractApprover::query()
            ->where('user_id', $userId)
            ->where('status', ContractApprover::STATUS_PENDING)
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->count();

        $myInReview = Contract::query()
            ->where('responsible_id', $userId)
            ->where('status', Contract::STATUS_IN_REVIEW)
            ->count();

        $myApproved = Contract::query()
            ->where('responsible_id', $userId)
            ->where('status', Contract::STATUS_APPROVED)
            ->count();

        $myDrafts = Contract::query()
            ->where('responsible_id', $userId)
            ->where('status', Contract::STATUS_DRAFT)
            ->count();

        $stats = [
            Stat::make(__('app.tab.awaiting_me'), $awaiting)
                ->description($overdue > 0
                    ? __('app.stats.overdue_count', ['count' => $overdue])
                    : __('app.stats.on_track'))
                ->descriptionIcon($overdue > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($overdue > 0 ? 'danger' : ($awaiting > 0 ? 'warning' : 'gray'))
                ->url($listUrl.'?activeTab=awaiting_me'),
        ];

        // Manager-only stats — director/legal don't own contracts the same way,
        // so "my drafts" / "my in review" would always be 0 for them. Skip them.
        if ($user?->hasAnyRole(['manager', 'super_admin'])) {
            $stats[] = Stat::make(__('app.stats.my_in_review'), $myInReview)
                ->descriptionIcon('heroicon-m-clock')
                ->color($myInReview > 0 ? 'warning' : 'gray')
                ->url($listUrl.'?activeTab=my_contracts&tableFilters[status][value]='.Contract::STATUS_IN_REVIEW->value);

            $stats[] = Stat::make(__('app.stats.my_approved'), $myApproved)
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('success')
                ->url($listUrl.'?activeTab=my_contracts&tableFilters[status][value]='.Contract::STATUS_APPROVED->value);

            $stats[] = Stat::make(__('app.stats.my_drafts'), $myDrafts)
                ->descriptionIcon('heroicon-m-pencil-square')
                ->color('gray')
                ->url($listUrl.'?activeTab=my_contracts&tableFilters[status][value]='.Contract::STATUS_DRAFT->value);
        }

        return $stats;
    }
}
