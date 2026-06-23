<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Models\Contract;
use App\Models\ContractApprover;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final class DashboardContext
{
    private ?User $user;

    /** @var array<string, mixed> */
    private array $memo = [];

    public function __construct()
    {
        $this->user = auth()->user();
    }

    public function user(): ?User
    {
        return $this->user;
    }

    public function firstName(): string
    {
        $name = trim((string) ($this->user?->name ?? ''));

        if ($name === '') {
            return '';
        }

        return explode(' ', $name)[0];
    }

    public function greetingHeading(): string
    {
        $name = $this->firstName();

        return $name !== ''
            ? __('app.dashboard.greeting_named', ['name' => $name])
            : __('app.dashboard.greeting');
    }

    /**
     * The single line under the greeting that names whatever is most pressing
     * for this user right now — overdue work, then awaiting work, then a
     * manager's stalled contracts, otherwise an all-clear.
     */
    public function summaryLine(): string
    {
        $overdue = $this->isApprover() ? $this->overdueForMe()->count() : 0;
        $awaiting = $this->isApprover() ? $this->awaitingMe()->count() : 0;
        $stalled = $this->isManager() ? $this->managerCounts()['stalled'] : 0;

        if ($overdue > 0) {
            return __('app.dashboard.summary_overdue', ['count' => $overdue, 'total' => $awaiting]);
        }

        if ($awaiting > 0) {
            return __('app.dashboard.summary_awaiting', ['count' => $awaiting]);
        }

        if ($stalled > 0) {
            return __('app.dashboard.summary_stalled', ['count' => $stalled]);
        }

        return __('app.dashboard.summary_clear');
    }

    public function isApprover(): bool
    {
        return $this->user?->hasAnyRole(['director', 'legal_officer', 'accountant', 'super_admin']) ?? false;
    }

    public function isManager(): bool
    {
        return $this->user?->hasAnyRole(['manager', 'super_admin']) ?? false;
    }

    public function isFinance(): bool
    {
        return $this->user?->hasAnyRole(['accountant', 'director', 'super_admin']) ?? false;
    }

    public function isOversight(): bool
    {
        return $this->user?->hasAnyRole(Contract::OVERSIGHT_ROLES) ?? false;
    }

    public function isSuperAdmin(): bool
    {
        return $this->user?->hasRole('super_admin') ?? false;
    }

    /**
     * Contracts that are parked on THIS user right now — they're the current
     * pending approver and no earlier step is still pending.
     *
     * @return Collection<int, Contract>
     */
    public function awaitingMe(): Collection
    {
        return $this->memo['awaitingMe'] ??= Contract::query()
            ->awaitingApprovalBy($this->user)
            ->with(['contact', 'currency', 'responsible', 'activeApprovers'])
            ->get();
    }

    /**
     * Subset of awaitingMe() whose review clock has already run out.
     *
     * @return Collection<int, Contract>
     */
    public function overdueForMe(): Collection
    {
        return $this->memo['overdueForMe'] ??= $this->awaitingMe()
            ->filter(fn (Contract $c) => $this->myApproverRow($c)?->isOverdue())
            ->values();
    }

    /**
     * For managers: their own in-review contracts whose current approver has
     * blown the SLA — "someone is sitting on my contract".
     *
     * @return Collection<int, Contract>
     */
    public function myStalledContracts(): Collection
    {
        if (! $this->user) {
            return collect();
        }

        return $this->memo['myStalled'] ??= Contract::query()
            ->where('responsible_id', $this->user->id)
            ->where('status', Contract::STATUS_IN_REVIEW)
            ->with(['activeApprovers.user'])
            ->get()
            ->filter(fn (Contract $c) => $c->currentApprover()?->isOverdue())
            ->values();
    }

    public function myApproverRow(Contract $contract): ?ContractApprover
    {
        if (! $this->user) {
            return null;
        }

        return $contract->activeApprovers
            ->firstWhere(fn (ContractApprover $a) => $a->user_id === $this->user->id
                && $a->status === ContractApprover::STATUS_PENDING);
    }

    /**
     * Counts that feed the manager's personal stat strip.
     *
     * @return array{drafts: int, in_review: int, rejected: int, stalled: int}
     */
    public function managerCounts(): array
    {
        if (! $this->user) {
            return ['drafts' => 0, 'in_review' => 0, 'rejected' => 0, 'stalled' => 0];
        }

        return $this->memo['managerCounts'] ??= [
            'drafts' => Contract::query()
                ->where('responsible_id', $this->user->id)
                ->where('status', Contract::STATUS_DRAFT)
                ->count(),
            'in_review' => Contract::query()
                ->where('responsible_id', $this->user->id)
                ->where('status', Contract::STATUS_IN_REVIEW)
                ->count(),
            'rejected' => Contract::query()
                ->where('responsible_id', $this->user->id)
                ->where('status', Contract::STATUS_REJECTED)
                ->count(),
            'stalled' => $this->myStalledContracts()->count(),
        ];
    }

    /**
     * Weekly sparkline series for the manager's stat strip — one integer per
     * week over the trailing window. Drafts are bucketed by when the contract
     * was created; in-review and rejected by when the row last moved, a close
     * proxy for when it entered that state. Rows older than the window (or with
     * a future timestamp) fall outside it and are ignored.
     *
     * @return array{drafts: list<int>, in_review: list<int>, rejected: list<int>}
     */
    public function managerStatusTrends(int $weeks = 8): array
    {
        /** @var list<int> $empty */
        $empty = array_fill(0, $weeks, 0);

        if (! $this->user) {
            return ['drafts' => $empty, 'in_review' => $empty, 'rejected' => $empty];
        }

        return $this->memo['managerStatusTrends'] ??= (function () use ($weeks, $empty): array {
            $windowStart = CarbonImmutable::now()->startOfWeek()->subWeeks($weeks - 1);

            $series = [
                Contract::STATUS_DRAFT->value => $empty,
                Contract::STATUS_IN_REVIEW->value => $empty,
                Contract::STATUS_REJECTED->value => $empty,
            ];

            Contract::query()
                ->where('responsible_id', $this->user->id)
                ->whereIn('status', [Contract::STATUS_DRAFT, Contract::STATUS_IN_REVIEW, Contract::STATUS_REJECTED])
                ->get(['status', 'created_at', 'updated_at'])
                ->each(function (Contract $contract) use (&$series, $windowStart, $weeks): void {
                    $movedAt = $contract->status === Contract::STATUS_DRAFT
                        ? $contract->created_at
                        : $contract->updated_at;

                    if ($movedAt === null) {
                        return;
                    }

                    $week = CarbonImmutable::make($movedAt)->startOfWeek();
                    $index = (int) round($windowStart->diffInDays($week, false) / 7);

                    if ($index >= 0 && $index < $weeks) {
                        $series[$contract->status->value][$index]++;
                    }
                });

            // Accumulate into a running total so the sparkline reads as a
            // visible rising trend even when there are only one or two
            // contracts — a raw weekly count would be a near-flat line.
            $runningTotal = function (array $weekly): array {
                $total = 0;

                return array_map(function (int $count) use (&$total): int {
                    return $total += $count;
                }, array_values($weekly));
            };

            return [
                'drafts' => $runningTotal($series[Contract::STATUS_DRAFT->value]),
                'in_review' => $runningTotal($series[Contract::STATUS_IN_REVIEW->value]),
                'rejected' => $runningTotal($series[Contract::STATUS_REJECTED->value]),
            ];
        })();
    }
}
