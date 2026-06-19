<?php

use App\Filament\Resources\Contracts\Pages\EditContract;
use App\Models\Contact;
use App\Models\Contract;
use App\Models\ContractApprover;
use App\Models\ContractTemplate;
use App\Models\Currency;
use App\Models\Department;
use App\Models\OrderType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function editorWithPerms(): User
{
    $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
    foreach (['view_any_contract', 'view_contract', 'update_contract'] as $p) {
        Permission::findOrCreate($p, 'web');
        $user->givePermissionTo($p);
    }
    $user->assignRole(Role::findOrCreate('super_admin', 'web'));
    actingAs($user->fresh());

    return $user;
}

/** An active user in an approver department, so the picker accepts them. */
function chainApprover(): User
{
    $dept = Department::firstOrCreate(['code' => 'legal'], [
        'name' => ['ru' => 'Legal', 'uz' => 'Legal', 'en' => 'Legal'],
        'sort' => 1,
        'status' => true,
    ]);

    return User::factory()->create([
        'status' => User::STATUS_ACTIVE,
        'department_id' => $dept->id,
    ]);
}

/** A valid in-review contract (all required Basic-Info fields populated). */
function inReviewContractFor(User $author): Contract
{
    $orderType = OrderType::factory()->create();
    $template = ContractTemplate::factory()->create(['order_type_id' => $orderType->id, 'status' => true]);

    return Contract::factory()->create([
        'responsible_id' => $author->id,
        'status' => Contract::STATUS_IN_REVIEW,
        'number' => 'C-CHAIN-'.fake()->unique()->numberBetween(1000, 9999),
        'order_type_id' => $orderType->id,
        'contract_template_id' => $template->id,
        'contact_id' => Contact::factory()->create(['status' => true])->id,
        'currency_id' => Currency::factory()->create(['status' => true])->id,
        'amount' => 1000,
    ]);
}

it('shows the approval chain tab on edit and pre-fills it with the queued chain', function () {
    $author = editorWithPerms();
    $first = User::factory()->create(['name' => 'Alisher Y', 'status' => User::STATUS_ACTIVE]);
    $second = User::factory()->create(['name' => 'Madina S', 'status' => User::STATUS_ACTIVE]);

    $contract = Contract::factory()->create([
        'responsible_id' => $author->id,
        'status' => Contract::STATUS_DRAFT,
    ]);

    ContractApprover::factory()->create([
        'contract_id' => $contract->id,
        'user_id' => $first->id,
        'order' => 1,
        'status' => ContractApprover::STATUS_QUEUED,
    ]);
    ContractApprover::factory()->create([
        'contract_id' => $contract->id,
        'user_id' => $second->id,
        'order' => 2,
        'status' => ContractApprover::STATUS_QUEUED,
    ]);

    Livewire::test(EditContract::class, ['record' => $contract->getKey()])
        ->assertFormFieldExists('approver_chain')
        ->assertFormSet(['approver_chain' => [$first->id, $second->id]]);
});

it('reassigns the chain on an in-review contract: resets to draft, keeps the old chain as audit', function () {
    $author = editorWithPerms();
    $current = chainApprover();
    $next = chainApprover();
    $replacement = chainApprover();

    $contract = inReviewContractFor($author);

    ContractApprover::factory()->create([
        'contract_id' => $contract->id, 'user_id' => $current->id, 'order' => 1,
        'status' => ContractApprover::STATUS_PENDING,
    ]);
    ContractApprover::factory()->create([
        'contract_id' => $contract->id, 'user_id' => $next->id, 'order' => 2,
        'status' => ContractApprover::STATUS_QUEUED,
    ]);

    // Swap the whole chain to one new approver — no business field touched.
    Livewire::test(EditContract::class, ['record' => $contract->getKey()])
        ->assertFormSet(['approver_chain' => [$current->id, $next->id]])
        ->fillForm(['approver_chain' => [$replacement->id]])
        ->call('save')
        ->assertHasNoFormErrors();

    $contract->refresh();

    // Reset to draft, old chain invalidated for audit, new queue from the picker.
    expect($contract->status)->toBe(Contract::STATUS_DRAFT)
        ->and($contract->approvers()->where('status', ContractApprover::STATUS_INVALIDATED)->count())->toBe(2)
        ->and($contract->activeApprovers()->pluck('user_id')->map(fn ($id): int => (int) $id)->all())->toBe([$replacement->id]);
});

it('leaves a running approval untouched when the chain is saved unchanged', function () {
    $author = editorWithPerms();
    $current = chainApprover();

    $contract = inReviewContractFor($author);
    ContractApprover::factory()->create([
        'contract_id' => $contract->id, 'user_id' => $current->id, 'order' => 1,
        'status' => ContractApprover::STATUS_PENDING,
    ]);

    // Open and save without changing the chain or any business field.
    Livewire::test(EditContract::class, ['record' => $contract->getKey()])
        ->call('save')
        ->assertHasNoFormErrors();

    $contract->refresh();

    expect($contract->status)->toBe(Contract::STATUS_IN_REVIEW)
        ->and($contract->approvers()->where('status', ContractApprover::STATUS_INVALIDATED)->count())->toBe(0)
        ->and($contract->activeApprovers()->pluck('user_id')->map(fn ($id): int => (int) $id)->all())->toBe([$current->id]);
});
