<?php

use App\Models\Contract;
use App\Models\ContractApprover;
use App\Models\User;
use App\Services\Contracts\ContractWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/** A single active user holding the director role. */
function makeDirector(): User
{
    Role::firstOrCreate(['name' => Contract::DIRECTOR_ROLE, 'guard_name' => 'web']);

    $director = User::factory()->create(['status' => User::STATUS_ACTIVE]);
    $director->assignRole(Contract::DIRECTOR_ROLE);

    return $director;
}

/**
 * An in-review contract with a lawyer (pending) + accountant (queued) chain.
 *
 * @return array{Contract, User, User, User}
 */
function inReviewWithLawyerAccountant(): array
{
    $responsible = User::factory()->create();
    $lawyer = User::factory()->create(['status' => User::STATUS_ACTIVE]);
    $accountant = User::factory()->create(['status' => User::STATUS_ACTIVE]);

    $contract = Contract::factory()->create([
        'responsible_id' => $responsible->id,
        'status' => Contract::STATUS_IN_REVIEW,
    ]);

    ContractApprover::factory()->create([
        'contract_id' => $contract->id, 'user_id' => $lawyer->id, 'order' => 1,
        'status' => ContractApprover::STATUS_PENDING,
    ]);
    ContractApprover::factory()->create([
        'contract_id' => $contract->id, 'user_id' => $accountant->id, 'order' => 2,
        'status' => ContractApprover::STATUS_QUEUED,
    ]);

    return [$contract->refresh(), $responsible, $lawyer, $accountant];
}

/** Approve through the lawyer + accountant stage. */
function approveLawyerAndAccountant(Contract $contract, User $lawyer, User $accountant): void
{
    $workflow = app(ContractWorkflow::class);

    actingAs($lawyer);
    $workflow->approve($contract, $lawyer);

    actingAs($accountant);
    $workflow->approve($contract->fresh(), $accountant);
}

it('parks the contract in pending_director after lawyer + accountant approve', function () {
    makeDirector();
    [$contract, $responsible, $lawyer, $accountant] = inReviewWithLawyerAccountant();

    approveLawyerAndAccountant($contract, $lawyer, $accountant);

    $contract->refresh();

    // Not auto-sent to the director and not approved — parked for a manual hand-off.
    expect($contract->status)->toBe(Contract::STATUS_PENDING_DIRECTOR)
        ->and($contract->hasDirectorApprover())->toBeFalse()
        ->and($contract->canBeSentToDirectorBy($responsible))->toBeTrue();
});

it('sends to the director manually and finalizes on the director sign-off', function () {
    $director = makeDirector();
    [$contract, $responsible, $lawyer, $accountant] = inReviewWithLawyerAccountant();

    approveLawyerAndAccountant($contract, $lawyer, $accountant);

    // Manager hands it to the director.
    actingAs($responsible);
    expect(app(ContractWorkflow::class)->submitToDirector($contract->fresh()))->toBeTrue();

    $contract->refresh();
    expect($contract->status)->toBe(Contract::STATUS_IN_REVIEW)
        ->and($contract->isInDirectorStage())->toBeTrue();

    $directorRow = $contract->activeApprovers()->where('user_id', $director->id)->first();
    expect($directorRow)->not->toBeNull()
        ->and($directorRow->status)->toBe(ContractApprover::STATUS_PENDING)
        ->and($directorRow->order)->toBe(3);

    // Director signs off → fully approved.
    actingAs($director);
    app(ContractWorkflow::class)->approve($contract->fresh(), $director);

    $contract->refresh();
    expect($contract->status)->toBe(Contract::STATUS_APPROVED)
        ->and($contract->signed_at)->not->toBeNull();
});

it('freezes editing for the author from the pending_director stage onward', function () {
    $director = makeDirector();
    [$contract, $responsible, $lawyer, $accountant] = inReviewWithLawyerAccountant();

    // While the lawyer + accountant are reviewing the author can still edit.
    expect($contract->canBeEditedBy($responsible))->toBeTrue();

    approveLawyerAndAccountant($contract, $lawyer, $accountant);

    // Parked for the director → editing frozen.
    expect($contract->fresh()->canBeEditedBy($responsible))->toBeFalse();

    actingAs($responsible);
    app(ContractWorkflow::class)->submitToDirector($contract->fresh());

    // With the director reviewing → still frozen.
    expect($contract->fresh()->canBeEditedBy($responsible))->toBeFalse();
});

it('rejects sending to the director by someone who is not the manager', function () {
    makeDirector();
    [$contract, , $lawyer, $accountant] = inReviewWithLawyerAccountant();

    approveLawyerAndAccountant($contract, $lawyer, $accountant);

    $outsider = User::factory()->create();
    actingAs($outsider);

    expect(app(ContractWorkflow::class)->submitToDirector($contract->fresh()))->toBeFalse()
        ->and($contract->fresh()->status)->toBe(Contract::STATUS_PENDING_DIRECTOR);
});

it('finalizes as approved after lawyer + accountant when no director is configured', function () {
    [$contract, , $lawyer, $accountant] = inReviewWithLawyerAccountant();

    approveLawyerAndAccountant($contract, $lawyer, $accountant);

    $contract->refresh();
    expect($contract->status)->toBe(Contract::STATUS_APPROVED)
        ->and($contract->activeApprovers()->count())->toBe(2);
});
