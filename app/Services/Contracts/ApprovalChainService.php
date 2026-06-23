<?php

namespace App\Services\Contracts;

use App\Models\Department;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

class ApprovalChainService
{
    /**
     * @return array<int, int>
     */
    public function defaultApproverIds(?User $user = null): array
    {
        $user ??= Auth::user();

        if (! $user) {
            return [];
        }

        $ids = $user->defaultRecipients()
            ->where('users.status', User::STATUS_ACTIVE)
            ->pluck('users.id')
            ->all();

        if (empty($ids)) {
            $ids = collect(Department::approvalFlow())
                ->map(fn (string $code) => Department::findByCode($code)?->approverUser()?->id)
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        return array_map('intval', $ids);
    }

    public function validateRequiredDepartments(mixed $value, Closure $fail): void
    {
        $ids = $this->normaliseIds($value);

        if ($ids === []) {
            return;
        }

        $presentCodes = User::query()
            ->whereIn('id', $ids)
            ->with('department')
            ->get()
            ->map(fn (User $user): ?string => $user->department?->code)
            ->filter()
            ->unique();

        $missing = collect(Department::REQUIRED_APPROVER_CODES)
            ->reject(fn (string $code): bool => $presentCodes->contains($code));

        if ($missing->isNotEmpty()) {
            $fail(__('app.validation.approver_chain_required_departments'));
        }
    }

    public function previewHtml(mixed $value): HtmlString
    {
        $ids = $this->normaliseIds($value);

        if ($ids === []) {
            return new HtmlString(view('filament.resources.contracts.partials.approval-chain-preview', [
                'users' => collect(),
                'ids' => [],
            ])->render());
        }

        return new HtmlString(view('filament.resources.contracts.partials.approval-chain-preview', [
            'users' => User::with(['department', 'position'])->whereIn('id', $ids)->get()->keyBy('id'),
            'ids' => $ids,
        ])->render());
    }

    /**
     * @return array<int, int>
     */
    private function normaliseIds(mixed $value): array
    {
        return array_values(array_filter(array_map('intval', (array) $value)));
    }
}
