<?php

use App\Filament\Pages\Dashboard;
use App\Filament\Resources\Contracts\Pages\ListContracts;
use App\Filament\Widgets\ContractStatsWidget;
use App\Filament\Widgets\Dashboard\MyContractsInReviewWidget;
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

it('greets the user as the page heading and offers a New-contract action to authors', function () {
    $user = dashboardUser();
    Permission::findOrCreate('create_contract', 'web');
    $user->givePermissionTo('create_contract');

    actingAs($user->fresh());

    Livewire::test(Dashboard::class)
        ->assertOk()
        ->assertSee(__('app.dashboard.greeting'))
        ->assertSee(__('app.action.create_contract'));
});

it('shows the author their own contracts that are in review, not drafts or others', function () {
    $manager = dashboardUser();

    $inReview = Contract::factory()->inReview()->create(['responsible_id' => $manager->id]);
    $draft = Contract::factory()->create(['responsible_id' => $manager->id]);
    $someoneElses = Contract::factory()->inReview()->create();

    actingAs($manager);

    expect(MyContractsInReviewWidget::canView())->toBeTrue();

    Livewire::test(MyContractsInReviewWidget::class)
        ->assertCanSeeTableRecords([$inReview])
        ->assertCanNotSeeTableRecords([$draft, $someoneElses]);
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
