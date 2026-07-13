<?php

use App\Filament\Pages\Dashboard;
use App\Filament\Resources\Contracts\Pages\ListContracts;
use App\Filament\Widgets\Dashboard\DashboardHeaderWidget;
use App\Models\Contract;
use App\Models\ContractApprover;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

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

it('offers a create-contract action in the header to users who may create', function () {
    $manager = User::factory()->create();
    Permission::findOrCreate('create_contract', 'web');
    $manager->givePermissionTo('create_contract');
    actingAs($manager->fresh());

    Livewire::test(DashboardHeaderWidget::class)
        ->assertSee(__('app.action.create_contract'));
});

it('hides the create-contract action from users who cannot create', function () {
    actingAs(dashboardUser());

    Livewire::test(DashboardHeaderWidget::class)
        ->assertDontSee(__('app.action.create_contract'));
});

it('greets the user as the dashboard page heading', function () {
    actingAs(dashboardUser());

    Livewire::test(Dashboard::class)
        ->assertOk()
        ->assertSee(__('app.dashboard.greeting'));
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
