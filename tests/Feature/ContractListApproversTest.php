<?php

use App\Filament\Resources\Contracts\Pages\ListContracts;
use App\Models\Contract;
use App\Models\ContractApprover;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function listOversight(): User
{
    $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
    Permission::findOrCreate('view_any_contract', 'web');
    $user->givePermissionTo('view_any_contract');
    $user->assignRole(Role::findOrCreate('super_admin', 'web'));
    actingAs($user->fresh());

    return $user;
}

it('renders the Approvers column with avatars that mount the contract flow action', function () {
    listOversight();

    $approver = User::factory()->create(['name' => 'Alisher Lawyer', 'status' => User::STATUS_ACTIVE]);

    $contract = Contract::factory()->create(['status' => Contract::STATUS_IN_REVIEW]);
    ContractApprover::factory()->create([
        'contract_id' => $contract->id,
        'user_id' => $approver->id,
        'order' => 1,
        'status' => ContractApprover::STATUS_PENDING,
    ]);

    $html = Livewire::test(ListContracts::class)->html();

    expect($html)
        ->toContain('Alisher Lawyer')                       // name in avatar title/alt
        ->toContain("mountTableAction('contractFlow'");     // whole cell opens the flow
});

it('renders the approval flow modal as a stepper of the active chain', function () {
    listOversight();

    $first = User::factory()->create(['name' => 'Madina Accountant']);
    $second = User::factory()->create(['name' => 'Olim Reviewer']);
    $contract = Contract::factory()->create(['status' => Contract::STATUS_IN_REVIEW]);

    ContractApprover::factory()->create([
        'contract_id' => $contract->id,
        'user_id' => $first->id,
        'order' => 1,
        'status' => ContractApprover::STATUS_APPROVED,
        'acted_at' => now(),
        'comment' => 'Looks fine to me',
    ]);
    ContractApprover::factory()->create([
        'contract_id' => $contract->id,
        'user_id' => $second->id,
        'order' => 2,
        'status' => ContractApprover::STATUS_PENDING,
    ]);

    $html = view('filament.resources.contracts.tables.approval-flow-modal', [
        'contract' => $contract->fresh(),
    ])->render();

    expect($html)
        ->toContain('Madina Accountant')
        ->toContain('Olim Reviewer')
        ->toContain('Looks fine to me')
        ->toContain('Approved')
        ->toContain('Reviewing');
});

it('lists invalidated attempts under previous attempts with comments preserved', function () {
    listOversight();

    $approver = User::factory()->create(['name' => 'Olim Repeated']);
    $contract = Contract::factory()->create(['status' => Contract::STATUS_IN_REVIEW]);

    // First attempt: approved then invalidated by an edit (comment preserved).
    ContractApprover::factory()->create([
        'contract_id' => $contract->id,
        'user_id' => $approver->id,
        'order' => 1,
        'status' => ContractApprover::STATUS_INVALIDATED,
        'comment' => 'Approved on first pass',
        'system_comment' => 'Cancelled — contract was edited.',
        'acted_at' => now()->subDays(2),
    ]);

    // Second attempt: currently reviewing again.
    ContractApprover::factory()->create([
        'contract_id' => $contract->id,
        'user_id' => $approver->id,
        'order' => 1,
        'status' => ContractApprover::STATUS_PENDING,
    ]);

    $html = view('filament.resources.contracts.tables.approval-flow-modal', [
        'contract' => $contract->fresh(),
    ])->render();

    expect($html)
        ->toContain('Olim Repeated')
        ->toContain('Approved on first pass')
        ->toContain('Cancelled — contract was edited.')
        ->toContain('is-inactive')
        ->toContain(__('app.contract_approver.status.invalidated'));
});
