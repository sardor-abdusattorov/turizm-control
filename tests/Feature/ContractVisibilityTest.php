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

    if ($role === 'manager') {
        // Managers are the ones who author contracts — make sure they have
        // the create permission so the "My contracts" tab is offered.
        Permission::findOrCreate('create_contract', 'web');
        $user->givePermissionTo('create_contract');
    }

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

it('lets a super admin see every contract, drafts included', function () {
    $admin = contractUser('super_admin');
    Contract::factory()->count(3)->create();

    expect(Contract::query()->visibleTo($admin)->count())->toBe(Contract::count());
});

it("hides other authors' drafts from the director but shows the live pipeline", function () {
    $director = contractUser('director');

    $othersDraft = Contract::factory()->create();
    $livePipeline = Contract::factory()->inReview()->create();
    $ownDraft = Contract::factory()->create(['responsible_id' => $director->id]);

    $visible = Contract::query()->visibleTo($director)->pluck('id');

    expect($visible)
        ->not->toContain($othersDraft->id)
        ->toContain($livePipeline->id)
        ->toContain($ownDraft->id);
});

it("keeps other authors' drafts unopenable by the director", function () {
    $director = contractUser('director');

    $othersDraft = Contract::factory()->create();
    $livePipeline = Contract::factory()->inReview()->create();
    $ownDraft = Contract::factory()->create(['responsible_id' => $director->id]);

    expect($othersDraft->canBeViewedBy($director))->toBeFalse()
        ->and($livePipeline->canBeViewedBy($director))->toBeTrue()
        ->and($ownDraft->canBeViewedBy($director))->toBeTrue();
});

it('hides the All tab from a manager but shows it to oversight roles', function () {
    actingAs(contractUser('manager'));
    expect(Livewire::test(ListContracts::class)->instance()->getTabs())
        ->not->toHaveKey('all');

    actingAs(contractUser('director'));
    $tabs = Livewire::test(ListContracts::class)->instance()->getTabs();
    expect($tabs)->toHaveKey('all')
        ->and(array_key_first($tabs))->toBe('all'); // All leads for oversight
});

function financeReviewer(string $role): User
{
    $user = contractUser($role);
    Permission::findOrCreate('view_all_contracts', 'web');
    $user->givePermissionTo('view_all_contracts');

    return $user->fresh();
}

it('shows the All tab to finance and legal reviewers, not just oversight', function () {
    foreach (['accountant', 'legal_officer'] as $role) {
        actingAs(financeReviewer($role));

        expect(Livewire::test(ListContracts::class)->instance()->getTabs())
            ->toHaveKey('all');
    }
});

it('lets a finance reviewer see the live pipeline but not other authors drafts', function () {
    $accountant = financeReviewer('accountant');

    $othersDraft = Contract::factory()->create();
    $livePipeline = Contract::factory()->inReview()->create();

    $visible = Contract::query()->visibleTo($accountant)->pluck('id');

    expect($visible)
        ->toContain($livePipeline->id)
        ->not->toContain($othersDraft->id);
});

it('lands a finance reviewer with nothing awaiting on the All tab', function () {
    $accountant = financeReviewer('accountant');

    Contract::factory()->inReview()->create(); // pipeline exists, but not theirs to action

    actingAs($accountant);

    expect(Livewire::test(ListContracts::class)->instance()->getDefaultActiveTab())
        ->toBe('all');
});

it('defaults an oversight user with no pending approvals to the All tab', function () {
    actingAs(contractUser('super_admin'));

    expect(Livewire::test(ListContracts::class)->instance()->getDefaultActiveTab())
        ->toBe('all');
});

it('drops the lone My contracts tab for a pure manager', function () {
    actingAs(contractUser('manager'));

    $page = Livewire::test(ListContracts::class)->instance();

    expect($page->getTabs())->toBe([])
        ->and($page->getDefaultActiveTab())->toBeNull();
});

it('hides the Responsible filter from a manager but shows it to oversight', function () {
    actingAs(contractUser('manager'));
    Livewire::test(ListContracts::class)->assertTableFilterHidden('responsible_id');

    actingAs(contractUser('director'));
    Livewire::test(ListContracts::class)->assertTableFilterVisible('responsible_id');
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
