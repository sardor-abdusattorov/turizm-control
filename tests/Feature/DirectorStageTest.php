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

it('auto-hands the contract to the director after lawyer + accountant approve', function () {
    $director = makeDirector();
    [$contract, , $lawyer, $accountant] = inReviewWithLawyerAccountant();

    $workflow = app(ContractWorkflow::class);

    actingAs($lawyer);
    $workflow->approve($contract, $lawyer);

    actingAs($accountant);
    $workflow->approve($contract->fresh(), $accountant);

    $contract->refresh();

    // Not approved yet — parked with the director as a fresh pending step.
    expect($contract->status)->toBe(Contract::STATUS_IN_REVIEW)
        ->and($contract->isInDirectorStage())->toBeTrue();

    $directorRow = $contract->activeApprovers()->where('user_id', $director->id)->first();
    expect($directorRow)->not->toBeNull()
        ->and($directorRow->status)->toBe(ContractApprover::STATUS_PENDING)
        ->and($directorRow->order)->toBe(3);

    // The director signs off → fully approved.
    actingAs($director);
    $workflow->approve($contract->fresh(), $director);

    $contract->refresh();
    expect($contract->status)->toBe(Contract::STATUS_APPROVED)
        ->and($contract->signed_at)->not->toBeNull()
        ->and($contract->isInDirectorStage())->toBeFalse();
});

it('locks editing for the author once the contract reaches the director stage', function () {
    $director = makeDirector();
    [$contract, $responsible, $lawyer, $accountant] = inReviewWithLawyerAccountant();

    // While the lawyer + accountant are still reviewing, the author can edit.
    expect($contract->canBeEditedBy($responsible))->toBeTrue();

    $workflow = app(ContractWorkflow::class);

    actingAs($lawyer);
    $workflow->approve($contract, $lawyer);

    actingAs($accountant);
    $workflow->approve($contract->fresh(), $accountant);

    // Handed to the director → editing is frozen for the author.
    expect($contract->fresh()->canBeEditedBy($responsible))->toBeFalse();
});

it('finalizes as approved after lawyer + accountant when no director is configured', function () {
    [$contract, , $lawyer, $accountant] = inReviewWithLawyerAccountant();

    $workflow = app(ContractWorkflow::class);

    actingAs($lawyer);
    $workflow->approve($contract, $lawyer);

    actingAs($accountant);
    $workflow->approve($contract->fresh(), $accountant);

    $contract->refresh();
    expect($contract->status)->toBe(Contract::STATUS_APPROVED)
        ->and($contract->isInDirectorStage())->toBeFalse()
        ->and($contract->activeApprovers()->count())->toBe(2);
});
