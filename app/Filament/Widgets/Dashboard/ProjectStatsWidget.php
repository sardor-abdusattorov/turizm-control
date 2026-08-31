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

class ProjectStatsWidget extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = -10;

    protected int|string|array $columnSpan = 'full';

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

        return count($points) > 1 ? $points : [0, ...($points ?: [0])];
    }

    protected function project(): ?Project
    {
        $projectId = Dashboard::filterValue($this->pageFilters['projectId'] ?? null);

        return $projectId ? Project::query()->find($projectId) : null;
    }
}
