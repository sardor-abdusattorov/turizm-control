<?php

use App\Enums\ContractDirection;
use App\Enums\ProjectType;
use App\Filament\Resources\Projects\Pages\CreateInternalProject;
use App\Filament\Resources\Projects\Pages\ViewInternalProject;
use App\Models\Contract;
use App\Models\ContractType;
use App\Models\Currency;
use App\Models\Order;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;

uses(RefreshDatabase::class);

it('splits contract totals by direction and currency', function () {
    $project = Project::factory()->create();

    $expense = ContractType::factory()->create();
    $income = ContractType::factory()->income()->create();

    $eur = Currency::factory()->create(['short_name' => 'EUR']);
    $usd = Currency::factory()->create(['short_name' => 'USD']);

    Contract::factory()->create(['project_id' => $project->id, 'contract_type_id' => $expense->id, 'currency_id' => $eur->id, 'amount' => 16595.41]);
    Contract::factory()->create(['project_id' => $project->id, 'contract_type_id' => $expense->id, 'currency_id' => $eur->id, 'amount' => 47890]);
    Contract::factory()->create(['project_id' => $project->id, 'contract_type_id' => $expense->id, 'currency_id' => $usd->id, 'amount' => 25000]);
    Contract::factory()->create(['project_id' => $project->id, 'contract_type_id' => $income->id, 'currency_id' => $usd->id, 'amount' => 38000]);
    // Rejected contracts stay out of the totals.
    Contract::factory()->rejected()->create(['project_id' => $project->id, 'contract_type_id' => $expense->id, 'currency_id' => $usd->id, 'amount' => 99999]);

    expect($project->contractTotalsByCurrency(ContractDirection::Expense))
        ->toBe(['EUR' => 64485.41, 'USD' => 25000.0])
        ->and($project->contractTotalsByCurrency(ContractDirection::Income))
        ->toBe(['USD' => 38000.0]);
});

it('carries its basis order directly and hands it to its contracts', function () {
    $delegation = Order::factory()->create(['title' => 'Командировочный 119-АФ']);
    $project = Project::factory()->create(['order_id' => $delegation->id]);
    $type = ContractType::factory()->create();

    $contract = Contract::factory()->create(['project_id' => $project->id, 'contract_type_id' => $type->id]);

    expect($project->order->id)->toBe($delegation->id)
        ->and($contract->project->order->id)->toBe($delegation->id);
});

it('shows visible-contract expense totals and the photo report on the view page', function () {
    $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);

    foreach (['view_any_project', 'view_project'] as $ability) {
        Permission::findOrCreate($ability, 'web');
        $user->givePermissionTo($ability);
    }

    $project = Project::factory()->create([
        'type' => ProjectType::Internal,
        'photo_report_url' => 'https://clck.ru/3UYYzh',
    ]);

    $expense = ContractType::factory()->create();
    $uzs = Currency::factory()->create(['short_name' => 'UZS']);

    // The viewer is responsible for this contract, so visibleTo() keeps it.
    Contract::factory()->create([
        'project_id' => $project->id,
        'contract_type_id' => $expense->id,
        'currency_id' => $uzs->id,
        'amount' => 1500000,
        'responsible_id' => $user->id,
    ]);

    actingAs($user->fresh());

    Livewire::test(ViewInternalProject::class, ['record' => $project->id])
        ->assertOk()
        ->assertSee('https://clck.ru/3UYYzh')
        ->assertSee('1 500 000');
});

it('saves the local-event fields through the internal project form', function () {
    $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);

    foreach (['view_any_project', 'create_project'] as $ability) {
        Permission::findOrCreate($ability, 'web');
        $user->givePermissionTo($ability);
    }

    actingAs($user->fresh());

    Livewire::test(CreateInternalProject::class)
        ->fillForm([
            'name' => 'Orol dengiziga poyezd',
            'starts_on' => '2026-05-20',
            'ends_on' => '2026-05-24',
            'photo_report_url' => 'https://clck.ru/3UYYzh',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    assertDatabaseHas(Project::class, [
        'name' => 'Orol dengiziga poyezd',
        'photo_report_url' => 'https://clck.ru/3UYYzh',
    ]);
});
