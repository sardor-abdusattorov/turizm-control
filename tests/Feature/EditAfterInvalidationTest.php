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

function approver(int $departmentId): User
{
    return User::factory()->create([
        'status' => User::STATUS_ACTIVE,
        'department_id' => $departmentId,
    ]);
}

function editorAuthor(User $profileDefault): User
{
    $author = User::factory()->create(['status' => User::STATUS_ACTIVE]);

    foreach (['view_any_contract', 'view_contract', 'update_contract'] as $ability) {
        Permission::findOrCreate($ability, 'web');
        $author->givePermissionTo($ability);
    }

    $author->defaultRecipients()->sync([$profileDefault->id]);

    actingAs($author->fresh());

    return $author->fresh();
}

it('keeps the invalidated chain visible and pre-fills the picker from the mirrored queue', function () {
    $legal = Department::factory()->create(['code' => 'legal']);
    $accounting = Department::factory()->create(['code' => 'accounting']);
    $profileDefault = approver($legal->id);
    $oldLegal = approver($legal->id);
    $oldAccounting = approver($accounting->id);

    $author = editorAuthor($profileDefault);

    $contractType = ContractType::factory()->create();

    $contract = Contract::factory()->create([
        'responsible_id' => $author->id,
        'status' => Contract::STATUS_IN_REVIEW,
        'number' => 'C-INV-1',
        'contract_type_id' => $contractType->id,
        'contact_id' => Contact::factory()->create(['status' => true])->id,
        'currency_id' => Currency::factory()->create(['status' => true])->id,
        'amount' => 1000,
    ]);

    ContractApprover::factory()->create([
        'contract_id' => $contract->id, 'user_id' => $oldLegal->id, 'order' => 1,
        'status' => ContractApprover::STATUS_PENDING,
    ]);
    ContractApprover::factory()->create([
        'contract_id' => $contract->id, 'user_id' => $oldAccounting->id, 'order' => 2,
        'status' => ContractApprover::STATUS_QUEUED,
    ]);

    Livewire::test(EditContract::class, ['record' => $contract->id])
        ->fillForm(['title' => 'Edited title'])
        ->call('save')
        ->assertHasNoFormErrors();

    $contract->refresh();

    expect($contract->status)->toBe(Contract::STATUS_DRAFT);
    expect($contract->approvers()->where('status', ContractApprover::STATUS_INVALIDATED)->count())->toBe(2);
    expect($contract->approvers()->where('status', ContractApprover::STATUS_QUEUED)->count())->toBe(2);

    Livewire::test(EditContract::class, ['record' => $contract->id])
        ->assertFormSet(['approver_chain' => [$oldLegal->id, $oldAccounting->id]]);
});
