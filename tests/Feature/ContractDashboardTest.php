<?php

use App\Filament\Resources\Contracts\Pages\ListContracts;
use App\Filament\Widgets\ContractStatsWidget;
use App\Filament\Widgets\ContractsTrendChartWidget;
use App\Models\Contract;
use App\Models\ContractApprover;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function dashboardUser(): User
{
    $user = User::factory()->create();

    foreach (['view_any_contract', 'view_contract'] as $ability) {
        Permission::findOrCreate($ability, 'web');
        $user->givePermissionTo($ability);
    }

    return $user;
}

it('renders the contract stats widget with a count of contracts awaiting the user', function () {
    $user = dashboardUser();
    $contract = Contract::factory()->create(['status' => Contract::STATUS_IN_REVIEW]);
    ContractApprover::factory()->create([
        'contract_id' => $contract->id,
        'user_id' => $user->id,
        'order' => 1,
        'due_at' => now()->subDay(),
    ]);

    actingAs($user);

    Livewire::test(ContractStatsWidget::class)->assertOk();
});

it('renders the contracts trend chart with grouped monthly aggregates', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('super_admin', 'web'));

    Contract::factory()->count(2)->create(['created_at' => now()->subMonth()]);
    Contract::factory()->approved()->create();

    actingAs($user->fresh());

    Livewire::test(ContractsTrendChartWidget::class)->assertOk();
});

it('renders the list page tabs and filters to contracts awaiting the user', function () {
    $user = dashboardUser();

    $mine = Contract::factory()->create(['status' => Contract::STATUS_IN_REVIEW]);
    ContractApprover::factory()->create([
        'contract_id' => $mine->id,
        'user_id' => $user->id,
        'order' => 1,
    ]);

    $other = Contract::factory()->create(['status' => Contract::STATUS_DRAFT]);

    actingAs($user);

    Livewire::test(ListContracts::class, ['activeTab' => 'awaiting_me'])
        ->assertOk()
        ->assertCanSeeTableRecords([$mine])
        ->assertCanNotSeeTableRecords([$other]);
});
