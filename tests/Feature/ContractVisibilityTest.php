<?php

use App\Filament\Resources\Contracts\Pages\ListContracts;
use App\Filament\Resources\Contracts\Pages\ViewContract;
use App\Models\Contract;
use App\Models\ContractApprover;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function contractUser(?string $role = null): User
{
    $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);

    Permission::findOrCreate('view_any_contract', 'web');
    $user->givePermissionTo('view_any_contract');

    if ($role) {
        $user->assignRole(Role::findOrCreate($role, 'web'));
    }

    return $user->fresh();
}

it('limits a manager to contracts they own or approve', function () {
    $manager = contractUser('manager');

    $own = Contract::factory()->create(['responsible_id' => $manager->id]);

    $involving = Contract::factory()->create(['status' => Contract::STATUS_IN_REVIEW]);
    ContractApprover::factory()->create([
        'contract_id' => $involving->id,
        'user_id' => $manager->id,
        'order' => 1,
    ]);

    $someoneElses = Contract::factory()->create();

    $visible = Contract::query()->visibleTo($manager)->pluck('id');

    expect($visible)->toContain($own->id)
        ->toContain($involving->id)
        ->not->toContain($someoneElses->id);
});

it('lets oversight roles see every contract', function (string $role) {
    $user = contractUser($role);
    Contract::factory()->count(3)->create();

    expect(Contract::query()->visibleTo($user)->count())->toBe(Contract::count());
})->with(['super_admin', 'director']);

it('hides the All tab from a manager but shows it to oversight roles', function () {
    actingAs(contractUser('manager'));
    expect(Livewire::test(ListContracts::class)->instance()->getTabs())
        ->not->toHaveKey('all');

    actingAs(contractUser('director'));
    expect(Livewire::test(ListContracts::class)->instance()->getTabs())
        ->toHaveKey('all');
});

it('defaults an oversight user with no pending approvals to the All tab', function () {
    actingAs(contractUser('super_admin'));

    expect(Livewire::test(ListContracts::class)->instance()->getDefaultActiveTab())
        ->toBe('all');
});

it('defaults a manager to their own contracts', function () {
    actingAs(contractUser('manager'));

    expect(Livewire::test(ListContracts::class)->instance()->getDefaultActiveTab())
        ->toBe('my_contracts');
});

it('defaults a user with pending approvals to the awaiting tab', function () {
    $approver = contractUser('legal_officer');

    $contract = Contract::factory()->create(['status' => Contract::STATUS_IN_REVIEW]);
    ContractApprover::factory()->create([
        'contract_id' => $contract->id,
        'user_id' => $approver->id,
        'order' => 1,
        'status' => ContractApprover::STATUS_PENDING,
    ]);

    actingAs($approver);

    expect(Livewire::test(ListContracts::class)->instance()->getDefaultActiveTab())
        ->toBe('awaiting_me');
});

it('stops a manager from opening a contract they are not involved in', function () {
    $manager = contractUser('manager');
    Permission::findOrCreate('view_contract', 'web');
    $manager->givePermissionTo('view_contract');

    $someoneElses = Contract::factory()->create();

    actingAs($manager->fresh());

    expect(fn () => Livewire::test(ViewContract::class, [
        'record' => $someoneElses->id,
    ]))->toThrow(ModelNotFoundException::class);
});
