<?php

use App\Models\Contract;
use App\Models\ContractApprover;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('queues the responsible user default recipients onto a chainless draft', function () {
    $responsible = User::factory()->create();
    $reviewers = User::factory()->count(2)->create(['status' => User::STATUS_ACTIVE]);
    $responsible->defaultRecipients()->sync($reviewers->pluck('id'));

    $draft = Contract::factory()->create([
        'responsible_id' => $responsible->id,
        'status' => Contract::STATUS_DRAFT,
    ]);

    expect($draft->approvers()->count())->toBe(0);

    $this->artisan('contracts:backfill-draft-chains')->assertSuccessful();

    expect($draft->fresh()->activeApprovers()->pluck('user_id')->sort()->values()->all())
        ->toBe($reviewers->pluck('id')->sort()->values()->all());
});

it('leaves a draft that already has a chain untouched', function () {
    $draft = Contract::factory()->create(['status' => Contract::STATUS_DRAFT]);
    $existing = ContractApprover::factory()->create([
        'contract_id' => $draft->id,
        'order' => 1,
        'status' => ContractApprover::STATUS_QUEUED,
    ]);

    $this->artisan('contracts:backfill-draft-chains')->assertSuccessful();

    expect($draft->fresh()->approvers()->pluck('id')->all())->toBe([$existing->id]);
});

it('does not touch contracts that are not drafts', function () {
    $contract = Contract::factory()->create(['status' => Contract::STATUS_IN_REVIEW]);

    $this->artisan('contracts:backfill-draft-chains')->assertSuccessful();

    expect($contract->fresh()->approvers()->count())->toBe(0);
});
