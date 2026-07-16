<?php

use App\Models\Contract;
use App\Models\ContractApprover;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\artisan;

uses(RefreshDatabase::class);

/**
 * The director stage (IN_REVIEW_DIRECTOR) must behave like the regular chain
 * stage everywhere a pending step surfaces: the «Ожидают меня» scope and the
 * SLA reminder command. Both used to filter on IN_REVIEW only, so a contract
 * sent to the director silently vanished from their queue and reminders.
 */
function directorStageContract(): array
{
    $chain = ContractApprover::factory()->approved()->create(['order' => 1]);
    $contract = $chain->contract;
    $contract->update(['status' => Contract::STATUS_IN_REVIEW_DIRECTOR]);

    $directorStep = ContractApprover::factory()->create([
        'contract_id' => $contract->id,
        'order' => 2,
        'due_at' => now()->addDay(),
    ]);

    return [$contract->refresh(), $directorStep->user];
}

it('lists a contract awaiting the director in their approval queue', function () {
    [$contract, $director] = directorStageContract();

    expect(Contract::query()->awaitingApprovalBy($director)->pluck('id'))
        ->toContain($contract->id);
});

it('reminds the director about an overdue review via the command', function () {
    [$contract, $director] = directorStageContract();
    $step = $contract->approvers()->where('user_id', $director->id)->first();
    $step->update(['due_at' => now()->subHours(2), 'reminder_sent_at' => null]);

    artisan('contracts:send-approval-reminders')->assertSuccessful();

    expect($step->fresh()->reminder_sent_at)->not->toBeNull();
});
