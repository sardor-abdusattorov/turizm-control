<?php

namespace App\Filament\Widgets\Dashboard;

use App\Enums\ContractDirection;
use App\Filament\Pages\Dashboard;
use App\Models\Contract;
use App\Models\Project;
use App\Support\Money;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Collection;

/**
 * The numbers of the project picked in the dashboard FilterAction: fees,
 * contract expense, contract count and participants — all derived from the
 * viewer-visible contracts, same rules as the project view page.
 */
class ProjectStatsWidget extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = -10;

    protected int|string|array $columnSpan = 'full';

    // Two money cards → 50% each, not squeezed into a four-column grid.
    protected int|array|null $columns = 2;

    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return auth()->user()?->can('view_any_project') ?? false;
    }

    protected function getStats(): array
    {
        $project = $this->project();

        if (! $project) {
            return [];
        }

        $contracts = $project->visibleContracts();

        $sumByDirection = fn (ContractDirection $direction) => $contracts
            ->filter(fn (Contract $contract): bool => $contract->contractType?->direction === $direction
                && $contract->status !== Contract::STATUS_REJECTED)
            ->groupBy(fn (Contract $contract): string => $contract->currency?->short_name ?? '')
            ->map(fn ($group) => $group->sum('amount'));

        $moneyLines = fn ($totals) => $totals
            ->map(fn ($value, $currency): string => Money::format($value).($currency ? ' '.$currency : ''))
            ->implode(' · ');

        $income = $sumByDirection(ContractDirection::Income);
        $expense = $sumByDirection(ContractDirection::Expense);

        $feesTotal = $project->feesTotal();
        $paidTotal = $project->paidTotal();
        $paidPercent = $feesTotal > 0 ? round($paidTotal / $feesTotal * 100) : 0;

        // Two money cards only (counts live on the project strip above), each
        // with the demo-style trend line: monthly totals of its direction.
        return [
            Stat::make(__('app.label.fees_total'), $income->isNotEmpty() ? $moneyLines($income) : __('app.label.not_set'))
                ->description(__('app.label.paid').': '.Money::format($paidTotal).' · '.$paidPercent.'%')
                ->descriptionIcon('heroicon-m-banknotes')
                ->chart($this->monthlyTotals($contracts, ContractDirection::Income))
                ->color('success'),

            Stat::make(__('app.contract.direction.expense').' · '.__('app.label.contracts'), $expense->isNotEmpty() ? $moneyLines($expense) : __('app.label.not_set'))
                ->description($project->stand_cost !== null
                    ? __('app.label.stand_cost').': '.Money::format($project->stand_cost).' '.($project->standCurrency?->short_name ?? '')
                    : null)
                ->descriptionIcon('heroicon-m-building-storefront')
                ->chart($this->monthlyTotals($contracts, ContractDirection::Expense))
                ->color('warning'),
        ];
    }

    /**
     * Monthly contract totals for the sparkline. Currency-blind by design:
     * the line draws the shape of activity, the exact figures live in the
     * value above it.
     *
     * @param  Collection<int, Contract>  $contracts
     * @return list<float>
     */
    protected function monthlyTotals($contracts, ContractDirection $direction): array
    {
        $points = $contracts
            ->filter(fn (Contract $contract): bool => $contract->contractType?->direction === $direction
                && $contract->status !== Contract::STATUS_REJECTED)
            ->groupBy(fn (Contract $contract): string => ($contract->signed_at ?? $contract->created_at)->format('Y-m'))
            ->sortKeys()
            ->map(fn ($group): float => (float) $group->sum('amount'))
            ->values()
            ->all();

        // A flat baseline still draws a line — one lone point would not.
        return count($points) > 1 ? $points : [0, ...($points ?: [0])];
    }

    protected function project(): ?Project
    {
        $projectId = Dashboard::filterValue($this->pageFilters['projectId'] ?? null);

        return $projectId ? Project::query()->find($projectId) : null;
    }
}
