<?php

use App\Filament\Resources\Contracts\Pages\EditContract;
use App\Models\Contract;
use App\Models\ContractApprover;
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
