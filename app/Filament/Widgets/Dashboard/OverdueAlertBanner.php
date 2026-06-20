<?php

namespace App\Filament\Widgets\Dashboard;

use App\Filament\Resources\Contracts\ContractResource;
use App\Models\Contract;
use App\Services\Dashboard\DashboardContext;
use Carbon\CarbonInterface;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

class OverdueAlertBanner extends Widget
{
    protected string $view = 'filament.widgets.dashboard.overdue-alert';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = -10;

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return self::resolveItems()->isNotEmpty();
    }

    /**
     * @return Collection<int, array{contract: Contract, who: ?string, days: int, role: string}>
     */
    public function items(): Collection
    {
        return self::resolveItems();
    }

    public function contractUrl(Contract $contract): string
    {
        return ContractResource::getUrl('view', ['record' => $contract]);
    }

    /**
     * @return Collection<int, array{contract: Contract, who: ?string, days: int, role: string}>
     */
    private static function resolveItems(): Collection
    {
        $context = app(DashboardContext::class);

        if ($context->isApprover() && $context->overdueForMe()->isNotEmpty()) {
            return $context->overdueForMe()->map(function (Contract $contract) use ($context) {
                $due = $context->myApproverRow($contract)?->due_at;

                return [
                    'contract' => $contract,
                    'who' => null,
                    'days' => $due ? (int) abs($due->diffInDays(now())) : 0,
                    'role' => 'mine',
                ];
            });
        }

        if ($context->isManager()) {
            return $context->myStalledContracts()->map(function (Contract $contract) {
                $current = $contract->currentApprover();
                $due = $current?->due_at;

                return [
                    'contract' => $contract,
                    'who' => $current?->user?->name,
                    'days' => $due ? (int) abs($due->diffInDays(now())) : 0,
                    'role' => 'theirs',
                ];
            });
        }

        return collect();
    }

    public function daysLabel(int $days): string
    {
        return trans_choice('app.dashboard.days_overdue', $days, ['count' => $days]);
    }

    public function diffForHumans(Contract $contract): ?string
    {
        return $contract->updated_at?->diffForHumans(now(), ['parts' => 1, 'syntax' => CarbonInterface::DIFF_ABSOLUTE]);
    }
}
