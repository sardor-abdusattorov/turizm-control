<?php

use App\Models\Contract;
use App\Models\ContractApprover;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The list page's "Awaiting my approval" tab + nav badge rely on
 * the awaitingApprovalBy / involvingApprover scopes returning
 * exactly the right contracts — these tests pin that down.
 */

it('returns contracts where I am the current approver and chain is in review', function (): void {
    $me = User::factory()->create();

    $contract = Contract::factory()->create(['status' => Contract::STATUS_IN_REVIEW]);
    ContractApprover::factory()->create([
        'contract_id' => $contract->id,
        'user_id' => $me->id,
        'order' => 1,
    ]);

    $result = Contract::query()->awaitingApprovalBy($me)->get();

    expect($result)->toHaveCount(1);
    expect($result->first()->id)->toBe($contract->id);
});

it('excludes contracts where an earlier approver is still pending', function (): void {
    $earlier = User::factory()->create();
    $me = User::factory()->create();

    $contract = Contract::factory()->create(['status' => Contract::STATUS_IN_REVIEW]);
    ContractApprover::factory()->create([
        'contract_id' => $contract->id,
        'user_id' => $earlier->id,
        'order' => 1,
    ]);
    ContractApprover::factory()->create([
        'contract_id' => $contract->id,
        'user_id' => $me->id,
        'order' => 2,
    ]);

    expect(Contract::query()->awaitingApprovalBy($me)->count())->toBe(0);
});

it('includes contracts where I am later but earlier approver has signed off', function (): void {
    $earlier = User::factory()->create();
    $me = User::factory()->create();

    $contract = Contract::factory()->create(['status' => Contract::STATUS_IN_REVIEW]);
    ContractApprover::factory()->create([
        'contract_id' => $contract->id,
        'user_id' => $earlier->id,
        'order' => 1,
        'status' => ContractApprover::STATUS_APPROVED,
        'acted_at' => now(),
    ]);
    ContractApprover::factory()->create([
        'contract_id' => $contract->id,
        'user_id' => $me->id,
        'order' => 2,
    ]);

    expect(Contract::query()->awaitingApprovalBy($me)->count())->toBe(1);
});

it('excludes draft and approved contracts from awaitingApprovalBy', function (): void {
    $me = User::factory()->create();

    foreach ([Contract::STATUS_DRAFT, Contract::STATUS_APPROVED, Contract::STATUS_REJECTED] as $status) {
        $contract = Contract::factory()->create(['status' => $status]);
        ContractApprover::factory()->create([
            'contract_id' => $contract->id,
            'user_id' => $me->id,
            'order' => 1,
        ]);
    }

    expect(Contract::query()->awaitingApprovalBy($me)->count())->toBe(0);
});

it('involvingApprover returns every contract where I appear on the chain', function (): void {
    $me = User::factory()->create();

    // I am a current pending approver
    $a = Contract::factory()->create(['status' => Contract::STATUS_IN_REVIEW]);
    ContractApprover::factory()->create([
        'contract_id' => $a->id,
        'user_id' => $me->id,
        'order' => 1,
    ]);

    // I have already approved
    $b = Contract::factory()->create(['status' => Contract::STATUS_APPROVED]);
    ContractApprover::factory()->create([
        'contract_id' => $b->id,
        'user_id' => $me->id,
        'order' => 1,
        'status' => ContractApprover::STATUS_APPROVED,
        'acted_at' => now(),
    ]);

    // I am not involved
    Contract::factory()->create();

    expect(Contract::query()->involvingApprover($me)->count())->toBe(2);
});
