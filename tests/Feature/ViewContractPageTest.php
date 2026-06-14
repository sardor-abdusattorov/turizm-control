<?php

use App\Filament\Resources\Contracts\Pages\ViewContract;
use App\Models\Contract;
use App\Models\ContractApprover;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function viewerWithAccess(): User
{
    $user = User::factory()->create();

    foreach (['view_any_contract', 'view_contract', 'update_contract'] as $ability) {
        Permission::findOrCreate($ability, 'web');
        $user->givePermissionTo($ability);
    }

    return $user;
}

it('renders the custom contract view page for a draft contract', function () {
    $user = viewerWithAccess();
    $contract = Contract::factory()->create([
        'responsible_id' => $user->id,
        'status' => Contract::STATUS_DRAFT,
    ]);

    actingAs($user);

    Livewire::test(ViewContract::class, ['record' => $contract->id])->assertOk();
});

it('renders the view page with an approval chain and history', function () {
    $user = viewerWithAccess();
    $approvers = User::factory()->count(2)->create();

    $contract = Contract::factory()->create([
        'responsible_id' => $user->id,
        'status' => Contract::STATUS_IN_REVIEW,
    ]);

    foreach ($approvers as $index => $approver) {
        ContractApprover::factory()->create([
            'contract_id' => $contract->id,
            'user_id' => $approver->id,
            'order' => $index + 1,
            'due_at' => now()->addDays(2),
        ]);
    }

    actingAs($user);

    Livewire::test(ViewContract::class, ['record' => $contract->id])
        ->assertOk()
        ->assertSee($approvers->first()->name);
});
