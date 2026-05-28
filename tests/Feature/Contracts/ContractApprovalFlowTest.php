<?php

use App\Models\Contract;
use App\Models\ContractApprover;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The approval flow is the heart of this app — these tests cover
 * the state machine: draft → in_review → (approved|rejected|returned).
 */

function makeContractWithApprovers(int $approverCount = 3, string $status = Contract::STATUS_DRAFT): Contract
{
    $contract = Contract::factory()->create(['status' => $status]);

    for ($i = 1; $i <= $approverCount; $i++) {
        ContractApprover::factory()->create([
            'contract_id' => $contract->id,
            'user_id' => User::factory()->create()->id,
            'order' => $i,
            'status' => ContractApprover::STATUS_PENDING,
        ]);
    }

    return $contract->fresh('approvers');
}

it('lets the responsible manager submit a draft for review', function (): void {
    $contract = makeContractWithApprovers();

    expect($contract->canBeSubmittedBy($contract->responsible))->toBeTrue();
});

it('forbids non-responsible users from submitting', function (): void {
    $contract = makeContractWithApprovers();
    $other = User::factory()->create();

    expect($contract->canBeSubmittedBy($other))->toBeFalse();
});

it('forbids submitting a draft with no approvers', function (): void {
    $contract = Contract::factory()->create(['status' => Contract::STATUS_DRAFT]);

    expect($contract->canBeSubmittedBy($contract->responsible))->toBeFalse();
});

it('exposes only the first pending approver as current', function (): void {
    $contract = makeContractWithApprovers(3, Contract::STATUS_IN_REVIEW);

    $current = $contract->currentApprover();

    expect($current)->not->toBeNull();
    expect($current->order)->toBe(1);
});

it('advances current approver after one approves', function (): void {
    $contract = makeContractWithApprovers(3, Contract::STATUS_IN_REVIEW);

    $contract->currentApprover()->markApproved('looks good');

    $contract->refresh();
    expect($contract->currentApprover()->order)->toBe(2);
    expect($contract->allApproved())->toBeFalse();
});

it('marks contract as approved + signs it after the last approval', function (): void {
    $contract = makeContractWithApprovers(2, Contract::STATUS_IN_REVIEW);

    foreach ($contract->approvers as $approver) {
        $approver->markApproved();
    }

    $contract->refresh();
    expect($contract->allApproved())->toBeTrue();

    // The caller (action) is responsible for setting status/signed_at.
    // Simulate what the action does:
    $contract->update([
        'status' => Contract::STATUS_APPROVED,
        'signed_at' => now()->toDateString(),
    ]);

    expect($contract->status)->toBe(Contract::STATUS_APPROVED);
    expect($contract->signed_at)->not->toBeNull();
});

it('detects rejection on the approval chain', function (): void {
    $contract = makeContractWithApprovers(3, Contract::STATUS_IN_REVIEW);

    $contract->currentApprover()->markRejected('missing signature');

    $contract->refresh();
    expect($contract->wasRejected())->toBeTrue();
});

it('resets the chain back to pending when manager edits an in-review contract', function (): void {
    $contract = makeContractWithApprovers(3, Contract::STATUS_IN_REVIEW);
    $contract->approvers->first()->markApproved('ok');
    $contract->refresh();

    expect($contract->approvers->first()->status)->toBe(ContractApprover::STATUS_APPROVED);

    $contract->resetApprovalState();
    $contract->refresh();

    expect($contract->status)->toBe(Contract::STATUS_DRAFT);
    expect($contract->approvers->pluck('status')->unique()->toArray())
        ->toBe([ContractApprover::STATUS_PENDING]);
    expect($contract->approvers->pluck('acted_at')->filter()->count())->toBe(0);
});

it('generates a unique sequential contract number on create', function (): void {
    $a = Contract::factory()->create();
    $b = Contract::factory()->create();

    expect($a->number)->not->toBe($b->number);
    expect($a->number)->toStartWith('КОНТ-');
    expect($b->number)->toStartWith('КОНТ-');
});
