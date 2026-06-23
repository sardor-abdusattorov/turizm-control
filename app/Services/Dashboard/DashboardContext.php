<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Models\Contract;
use App\Models\ContractApprover;
use App\Models\User;
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
     * @return array{drafts: int, in_review: int, stalled: int}
     */
    public function managerCounts(): array
    {
        if (! $this->user) {
            return ['drafts' => 0, 'in_review' => 0, 'stalled' => 0];
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
            'stalled' => $this->myStalledContracts()->count(),
        ];
    }
}
