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

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function validDraftContract(User $author): Contract
{
    $orderType = OrderType::factory()->create();
    $template = ContractTemplate::factory()->create(['order_type_id' => $orderType->id, 'status' => true]);

    return Contract::factory()->create([
        'responsible_id' => $author->id,
        'status' => Contract::STATUS_DRAFT,
        'number' => 'C-EDIT-'.fake()->unique()->numberBetween(1000, 9999),
        'order_type_id' => $orderType->id,
        'contract_template_id' => $template->id,
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

    $department = Department::factory()->create(['code' => 'legal']);
    $first = User::factory()->create(['status' => User::STATUS_ACTIVE, 'department_id' => $department->id]);
    $second = User::factory()->create(['status' => User::STATUS_ACTIVE, 'department_id' => $department->id]);
    $third = User::factory()->create(['status' => User::STATUS_ACTIVE, 'department_id' => $department->id]);

    $contract = validDraftContract($author);
    ContractApprover::factory()->create([
        'contract_id' => $contract->id, 'user_id' => $first->id, 'order' => 1,
        'status' => ContractApprover::STATUS_QUEUED,
    ]);

    // Replace [first] with [third, second] — order matters.
    Livewire::test(EditContract::class, ['record' => $contract->id])
        ->fillForm(['approver_chain' => [$third->id, $second->id]])
        ->call('save')
        ->assertHasNoFormErrors();

    $rows = $contract->fresh()->approvers()->orderBy('order')->get();

    expect($rows)->toHaveCount(2)
        ->and($rows[0]->user_id)->toBe($third->id)
        ->and($rows[0]->order)->toBe(1)
        ->and($rows[0]->status)->toBe(ContractApprover::STATUS_QUEUED)
        ->and($rows[1]->user_id)->toBe($second->id)
        ->and($rows[1]->order)->toBe(2);
});

it('hides the chain picker once the contract is in review', function () {
    $author = draftContractEditor();

    $contract = validDraftContract($author);
    $contract->update(['status' => Contract::STATUS_IN_REVIEW]);

    Livewire::test(EditContract::class, ['record' => $contract->id])
        ->assertFormFieldDoesNotExist('approver_chain');
});
