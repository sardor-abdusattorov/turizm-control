<?php

use App\Models\Contract;
use App\Models\ContractApprover;
use App\Models\User;
use App\Services\Contracts\ContractWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
});

function createContractWithChain(int $approverCount = 3): array
{
    $responsible = User::factory()->create();
    $approvers = asApprover(User::factory()->count($approverCount)->create());

    $contract = Contract::factory()->withDocument()->create([
        'responsible_id' => $responsible->id,
        'status' => Contract::STATUS_DRAFT,
    ]);

    foreach ($approvers as $index => $approver) {
        ContractApprover::factory()->create([
            'contract_id' => $contract->id,
            'user_id' => $approver->id,
            'order' => $index + 1,
        ]);
    }

    return [$contract->refresh(), $responsible, $approvers];
}

it('submits a contract for approval and notifies the first approver', function () {
    [$contract, $responsible, $approvers] = createContractWithChain();

    actingAs($responsible);

    $result = app(ContractWorkflow::class)->submit($contract);

    expect($result)->toBeTrue()
        ->and($contract->fresh()->status)->toBe(Contract::STATUS_IN_REVIEW);

    $this->assertDatabaseHas('notifications', [
        'notifiable_id' => $approvers->first()->id,
        'notifiable_type' => User::class,
    ]);
});

it('moves to the next approver when the current one approves', function () {
    [$contract, , $approvers] = createContractWithChain();
    $contract->update(['status' => Contract::STATUS_IN_REVIEW]);

    actingAs($approvers->first());

    $result = app(ContractWorkflow::class)->approve($contract, $approvers->first(), 'looks good');

    expect($result)->toBeTrue()
        ->and($contract->fresh()->status)->toBe(Contract::STATUS_IN_REVIEW);

    $approver = $contract->fresh()->approvers->first();
    expect($approver->status)->toBe(ContractApprover::STATUS_APPROVED)
        ->and($approver->comment)->toBe('looks good');

    $this->assertDatabaseHas('notifications', [
        'notifiable_id' => $approvers->get(1)->id,
        'notifiable_type' => User::class,
    ]);
});

it('marks the contract approved when the last approver approves and notifies the responsible', function () {
    [$contract, $responsible, $approvers] = createContractWithChain(2);
    $contract->update(['status' => Contract::STATUS_IN_REVIEW]);
    $contract->approvers()->where('order', 1)->first()->markApproved();

    actingAs($approvers->last());

    $result = app(ContractWorkflow::class)->approve($contract, $approvers->last());

    expect($result)->toBeTrue();

    $contract->refresh();
    expect($contract->status)->toBe(Contract::STATUS_APPROVED)
        ->and($contract->signed_at)->not->toBeNull();

    $this->assertDatabaseHas('notifications', [
        'notifiable_id' => $responsible->id,
        'notifiable_type' => User::class,
    ]);
});

it('rejects a contract and notifies the responsible', function () {
    [$contract, $responsible, $approvers] = createContractWithChain();
    $contract->update(['status' => Contract::STATUS_IN_REVIEW]);

    actingAs($approvers->first());

    $result = app(ContractWorkflow::class)->reject($contract, $approvers->first(), 'wrong amount');

    expect($result)->toBeTrue()
        ->and($contract->fresh()->status)->toBe(Contract::STATUS_REJECTED);

    $approver = $contract->fresh()->approvers->first();
    expect($approver->status)->toBe(ContractApprover::STATUS_REJECTED)
        ->and($approver->comment)->toBe('wrong amount');

    $this->assertDatabaseHas('notifications', [
        'notifiable_id' => $responsible->id,
        'notifiable_type' => User::class,
    ]);
});

it('refuses to approve when the acting user is not the current approver', function () {
    [$contract, , $approvers] = createContractWithChain();
    $contract->update(['status' => Contract::STATUS_IN_REVIEW]);

    $outsider = $approvers->get(1);
    actingAs($outsider);

    $result = app(ContractWorkflow::class)->approve($contract, $outsider);

    expect($result)->toBeFalse()
        ->and($contract->fresh()->status)->toBe(Contract::STATUS_IN_REVIEW);
});

it('refuses to submit when there are no approvers', function () {
    $responsible = User::factory()->create();
    $contract = Contract::factory()->withDocument()->create([
        'responsible_id' => $responsible->id,
        'status' => Contract::STATUS_DRAFT,
    ]);

    actingAs($responsible);

    $result = app(ContractWorkflow::class)->submit($contract);

    expect($result)->toBeFalse()
        ->and($contract->fresh()->status)->toBe(Contract::STATUS_DRAFT);
});

it('refuses to submit when the contract has no document on disk', function () {
    $responsible = User::factory()->create();
    $approver = User::factory()->create();

    // Note: no ->withDocument() — there is no docx on disk.
    $contract = Contract::factory()->create([
        'responsible_id' => $responsible->id,
        'status' => Contract::STATUS_DRAFT,
    ]);
    ContractApprover::factory()->create([
        'contract_id' => $contract->id, 'user_id' => $approver->id, 'order' => 1,
    ]);

    actingAs($responsible);

    expect($contract->canBeSubmittedBy($responsible))->toBeFalse()
        ->and(app(ContractWorkflow::class)->submit($contract))->toBeFalse()
        ->and($contract->fresh()->status)->toBe(Contract::STATUS_DRAFT);
});
