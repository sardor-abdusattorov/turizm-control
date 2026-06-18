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

it('renders an Approvers column with a button per approver that mounts the timeline action', function () {
    listOversight();

    $approver = User::factory()->create(['name' => 'Alisher Lawyer', 'status' => User::STATUS_ACTIVE]);

    $contract = Contract::factory()->create(['status' => Contract::STATUS_IN_REVIEW]);
    $row = ContractApprover::factory()->create([
        'contract_id' => $contract->id,
        'user_id' => $approver->id,
        'order' => 1,
        'status' => ContractApprover::STATUS_PENDING,
    ]);

    $html = Livewire::test(ListContracts::class)->html();

    expect($html)
        ->toContain('Alisher Lawyer')
        ->toContain("mountTableAction('approverTimeline'")
        ->toContain("approver: {$row->id}");
});

it('renders the approver timeline modal blade with the approver, status and activity', function () {
    listOversight();

    $approver = User::factory()->create(['name' => 'Madina Accountant']);
    $contract = Contract::factory()->create(['status' => Contract::STATUS_IN_REVIEW]);
    $row = ContractApprover::factory()->create([
        'contract_id' => $contract->id,
        'user_id' => $approver->id,
        'order' => 1,
        'status' => ContractApprover::STATUS_APPROVED,
        'acted_at' => now(),
        'comment' => 'Looks fine to me',
    ]);

    $html = view('filament.resources.contracts.tables.approver-timeline-modal', [
        'contract' => $contract->fresh(),
        'approver' => $row->fresh(),
    ])->render();

    expect($html)
        ->toContain('Madina Accountant')
        ->toContain('Looks fine to me')
        ->toContain('Approved')
        ->toContain('#1');
});
