<?php

use App\Enums\ContractStatus;
use App\Models\Contract;
use App\Models\ContractApprover;
use App\Models\User;
use App\Policies\ContractPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
});

/** Build a (responsible, current approver) pair and a contract in the given status. */
function permissionContext(ContractStatus $status, bool $withDocument = true): array
{
    $responsible = User::factory()->create(['status' => User::STATUS_ACTIVE]);
    $approver = User::factory()->create(['status' => User::STATUS_ACTIVE]);
    $contract = Contract::factory()
        ->when($withDocument, fn ($f) => $f->withDocument())
        ->create([
            'responsible_id' => $responsible->id,
            'status' => $status,
        ]);

    ContractApprover::factory()->create([
        'contract_id' => $contract->id,
        'user_id' => $approver->id,
        'order' => 1,
        'status' => $status === Contract::STATUS_IN_REVIEW
            ? ContractApprover::STATUS_PENDING
            : ContractApprover::STATUS_QUEUED,
    ]);

    return [$contract->refresh(), $responsible, $approver];
}

// ── canBeEditedBy ──────────────────────────────────────────────────

it('lets the responsible author edit a draft', function () {
    [$contract, $responsible] = permissionContext(Contract::STATUS_DRAFT);
    expect($contract->canBeEditedBy($responsible))->toBeTrue();
});

it('lets the responsible author edit an in-review contract', function () {
    [$contract, $responsible] = permissionContext(Contract::STATUS_IN_REVIEW);
    expect($contract->canBeEditedBy($responsible))->toBeTrue();
});

it('lets the responsible author edit a rejected contract', function () {
    [$contract, $responsible] = permissionContext(Contract::STATUS_REJECTED);
    expect($contract->canBeEditedBy($responsible))->toBeTrue();
});

it('freezes editing once the contract is pending director', function () {
    [$contract, $responsible] = permissionContext(Contract::STATUS_PENDING_DIRECTOR);
    expect($contract->canBeEditedBy($responsible))->toBeFalse();
});

it('freezes editing while the director is reviewing', function () {
    [$contract, $responsible] = permissionContext(Contract::STATUS_IN_REVIEW_DIRECTOR);
    expect($contract->canBeEditedBy($responsible))->toBeFalse();
});

it('freezes editing once the contract is approved — even for super_admin', function () {
    [$contract, $responsible] = permissionContext(Contract::STATUS_APPROVED);
    $admin = User::factory()->create();
    Role::findOrCreate('super_admin', 'web');
    $admin->assignRole('super_admin');

    expect($contract->canBeEditedBy($responsible))->toBeFalse()
        ->and($contract->canBeEditedBy($admin->fresh()))->toBeFalse();
});

it('lets the current approver edit while it is their turn', function () {
    [$contract, , $approver] = permissionContext(Contract::STATUS_IN_REVIEW);
    expect($contract->canBeEditedBy($approver))->toBeTrue();
});

it('denies editing for an unrelated user', function () {
    [$contract] = permissionContext(Contract::STATUS_IN_REVIEW);
    $outsider = User::factory()->create(['status' => User::STATUS_ACTIVE]);
    expect($contract->canBeEditedBy($outsider))->toBeFalse();
});

it('denies editing when no user is given', function () {
    [$contract] = permissionContext(Contract::STATUS_DRAFT);
    expect($contract->canBeEditedBy(null))->toBeFalse();
});

// ── canBeApprovedBy ────────────────────────────────────────────────

it('lets the current approver approve when status is InReview', function () {
    [$contract, , $approver] = permissionContext(Contract::STATUS_IN_REVIEW);
    expect($contract->canBeApprovedBy($approver))->toBeTrue();
});

it('denies approval to someone who is not the current approver', function () {
    [$contract, $responsible] = permissionContext(Contract::STATUS_IN_REVIEW);
    expect($contract->canBeApprovedBy($responsible))->toBeFalse();
});

it('denies approval on a draft contract', function () {
    [$contract, , $approver] = permissionContext(Contract::STATUS_DRAFT);
    expect($contract->canBeApprovedBy($approver))->toBeFalse();
});

it('denies approval on a rejected contract', function () {
    [$contract, , $approver] = permissionContext(Contract::STATUS_REJECTED);
    expect($contract->canBeApprovedBy($approver))->toBeFalse();
});

// ── canBeSentToDirectorBy ─────────────────────────────────────────

it('denies sending to director when no director role exists', function () {
    [$contract, $responsible] = permissionContext(Contract::STATUS_PENDING_DIRECTOR);
    expect($contract->canBeSentToDirectorBy($responsible))->toBeFalse();
});

it('lets the responsible send to director when one exists and status is pending_director', function () {
    Role::findOrCreate(Contract::DIRECTOR_ROLE, 'web');
    $director = User::factory()->create(['status' => User::STATUS_ACTIVE]);
    $director->assignRole(Contract::DIRECTOR_ROLE);

    [$contract, $responsible] = permissionContext(Contract::STATUS_PENDING_DIRECTOR);
    expect($contract->canBeSentToDirectorBy($responsible))->toBeTrue();
});

it('denies sending to director when contract is not in PendingDirector', function () {
    Role::findOrCreate(Contract::DIRECTOR_ROLE, 'web');
    $director = User::factory()->create(['status' => User::STATUS_ACTIVE]);
    $director->assignRole(Contract::DIRECTOR_ROLE);

    [$contract, $responsible] = permissionContext(Contract::STATUS_IN_REVIEW);
    expect($contract->canBeSentToDirectorBy($responsible))->toBeFalse();
});

it('denies sending to director when the user is not the responsible author', function () {
    Role::findOrCreate(Contract::DIRECTOR_ROLE, 'web');
    $director = User::factory()->create(['status' => User::STATUS_ACTIVE]);
    $director->assignRole(Contract::DIRECTOR_ROLE);

    [$contract] = permissionContext(Contract::STATUS_PENDING_DIRECTOR);
    $outsider = User::factory()->create(['status' => User::STATUS_ACTIVE]);
    expect($contract->canBeSentToDirectorBy($outsider))->toBeFalse();
});

// ── canBeDeletedBy ───────────────────────────────────────────────

it('lets the author delete their own draft', function () {
    [$contract, $responsible] = permissionContext(Contract::STATUS_DRAFT);
    expect($contract->canBeDeletedBy($responsible))->toBeTrue();
});

it('refuses to delete a contract once it is on review', function () {
    [$contract, $responsible] = permissionContext(Contract::STATUS_IN_REVIEW);
    expect($contract->canBeDeletedBy($responsible))->toBeFalse();
});

it('refuses to delete an approved contract — even for super_admin', function () {
    [$contract] = permissionContext(Contract::STATUS_APPROVED);

    $admin = User::factory()->create();
    Role::findOrCreate('super_admin', 'web');
    $admin->assignRole('super_admin');

    expect($contract->canBeDeletedBy($admin->fresh()))->toBeFalse();
});

it('makes the policy honour the status rule, not just the permission', function () {
    [$contract, $responsible] = permissionContext(Contract::STATUS_IN_REVIEW);
    $responsible->givePermissionTo(
        Permission::findOrCreate('delete_contract', 'web'),
    );

    // Permission alone isn't enough — the contract's own rule blocks it.
    expect(app(ContractPolicy::class)->delete($responsible->fresh(), $contract))
        ->toBeFalse();
});
