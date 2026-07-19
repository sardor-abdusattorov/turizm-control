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

    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return auth()->user()?->can('view_any_project') ?? false;
    }

    protected function getHeading(): ?string
    {
        $project = $this->project();

        if (! $project) {
            return __('app.label.project_single');
        }

        return $project->name;
    }

    protected function getDescription(): ?string
    {
        $project = $this->project();

        if (! $project) {
            return __('app.message.pulse_pick_project');
        }

        $parts = [];

        if ($project->starts_on) {
            $parts[] = $project->starts_on->format('d.m.Y')
                .($project->ends_on ? ' — '.$project->ends_on->format('d.m.Y') : '');
        }

        if ($project->venue) {
            $parts[] = $project->venue;
        }

        if ($project->area_sqm !== null) {
            $parts[] = Money::format($project->area_sqm).' м²';
        }

        return implode(' · ', $parts) ?: null;
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

        $members = $project->feeContracts()->visibleTo()
            ->where('status', '!=', Contract::STATUS_REJECTED->value)
            ->count();
        $sponsors = $project->sponsorshipContracts()->visibleTo()
            ->where('status', '!=', Contract::STATUS_REJECTED->value)
            ->count();

        return [
            Stat::make(__('app.label.fees_total'), $income->isNotEmpty() ? $moneyLines($income) : '—')
                ->description(__('app.label.paid').': '.Money::format($paidTotal).' · '.$paidPercent.'%')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make(__('app.contract.direction.expense').' · '.__('app.label.contracts'), $expense->isNotEmpty() ? $moneyLines($expense) : '—')
                ->description($project->stand_cost !== null
                    ? __('app.label.stand_cost').': '.Money::format($project->stand_cost).' '.($project->standCurrency?->short_name ?? '')
                    : null)
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->color('gray'),

            Stat::make(__('app.label.contracts'), $contracts->count())
                ->descriptionIcon('heroicon-m-document-text')
                ->color('info'),

            Stat::make(__('app.label.participants'), $members)
                ->description(__('app.label.sponsors').': '.$sponsors)
                ->descriptionIcon('heroicon-m-user-group')
                ->color('info'),
        ];
    }

    protected function project(): ?Project
    {
        $projectId = Dashboard::filterValue($this->pageFilters['projectId'] ?? null);

        return $projectId ? Project::query()->find($projectId) : null;
    }
}
