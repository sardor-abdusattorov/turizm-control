<?php

use App\Filament\Resources\Contracts\Pages\EditContract;
use App\Models\Contact;
use App\Models\Contract;
use App\Models\ContractApprover;
use App\Models\ContractType;
use App\Models\Currency;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function validDraftContract(User $author): Contract
{
    $contractType = ContractType::factory()->create();

    return Contract::factory()->create([
        'responsible_id' => $author->id,
        'status' => Contract::STATUS_DRAFT,
        'number' => 'C-EDIT-'.fake()->unique()->numberBetween(1000, 9999),
        'contract_type_id' => $contractType->id,
        'contact_id' => Contact::factory()->create(['status' => true])->id,
        'currency_id' => Currency::factory()->create(['status' => true])->id,
    ]);
}

function draftContractEditor(): User
{
    $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);

    foreach (['view_any_contract', 'view_contract', 'update_contract'] as $ability) {
        Permission::findOrCreate($ability, 'web');
        $user->givePermissionTo($ability);
    }

    actingAs($user->fresh());

    return $user;
}

it('lets the author swap the approver chain while the contract is a draft', function () {
    $author = draftContractEditor();

    $legalDept = Department::factory()->create(['code' => 'legal']);
    $accountingDept = Department::factory()->create(['code' => 'accounting']);
    $first = User::factory()->create(['status' => User::STATUS_ACTIVE, 'department_id' => $legalDept->id]);
    $accountant = User::factory()->create(['status' => User::STATUS_ACTIVE, 'department_id' => $accountingDept->id]);
    $newLawyer = User::factory()->create(['status' => User::STATUS_ACTIVE, 'department_id' => $legalDept->id]);

    $contract = validDraftContract($author);
    ContractApprover::factory()->create([
        'contract_id' => $contract->id, 'user_id' => $first->id, 'order' => 1,
        'status' => ContractApprover::STATUS_QUEUED,
    ]);

    // Replace [first] with [newLawyer, accountant] — order matters, both depts present.
    Livewire::test(EditContract::class, ['record' => $contract->id])
        ->fillForm(['approver_chain' => [$newLawyer->id, $accountant->id]])
        ->call('save')
        ->assertHasNoFormErrors();

    // The new chain is live, in order...
    $active = $contract->fresh()->activeApprovers()->orderBy('order')->get();
    expect($active)->toHaveCount(2)
        ->and($active[0]->user_id)->toBe($newLawyer->id)
        ->and($active[0]->order)->toBe(1)
        ->and($active[0]->status)->toBe(ContractApprover::STATUS_QUEUED)
        ->and($active[1]->user_id)->toBe($accountant->id)
        ->and($active[1]->order)->toBe(2);

    // ...and the removed approver was INVALIDATED, never deleted.
    expect($contract->fresh()->approvers()
        ->where('user_id', $first->id)
        ->where('status', ContractApprover::STATUS_INVALIDATED)
        ->exists())->toBeTrue();
});

it('shows and pre-fills the chain picker once the contract is in review', function () {
    $author = draftContractEditor();
    $approver = User::factory()->create(['status' => User::STATUS_ACTIVE]);

    $contract = validDraftContract($author);
    ContractApprover::factory()->create([
        'contract_id' => $contract->id, 'user_id' => $approver->id, 'order' => 1,
        'status' => ContractApprover::STATUS_PENDING,
    ]);
    $contract->update(['status' => Contract::STATUS_IN_REVIEW]);

    Livewire::test(EditContract::class, ['record' => $contract->id])
        ->assertFormFieldExists('approver_chain')
        ->assertFormSet(['approver_chain' => [$approver->id]]);
});
