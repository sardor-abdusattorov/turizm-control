<?php

use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\Dashboard\ProjectContractsTableWidget;
use App\Filament\Widgets\Dashboard\ProjectOverviewWidget;
use App\Filament\Widgets\Dashboard\ProjectParticipantsTableWidget;
use App\Filament\Widgets\Dashboard\ProjectStatsWidget;
use App\Models\Contact;
use App\Models\Contract;
use App\Models\ContractType;
use App\Models\Currency;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function dashboardProject(): array
{
    $project = Project::factory()->international()->create([
        'name' => 'ATM 25', 'starts_on' => now()->addWeek(), 'status' => true, 'venue' => 'Дубай',
    ]);
    $uzs = Currency::factory()->create(['short_name' => 'UZS']);
    $income = ContractType::factory()->income()->create();
    $viewer = userWithPermission('view_any_project', 'view_project');

    $fee = Contract::factory()->create([
        'project_id' => $project->id,
        'contract_type_id' => $income->id,
        'currency_id' => $uzs->id,
        'contact_id' => Contact::factory()->create(['name' => ['ru' => 'ООО «ZAMIN TRAVEL»', 'uz' => 'Z', 'en' => 'Z']])->id,
        'amount' => 10000000,
        'responsible_id' => $viewer->id,
        'number' => 'А-2',
    ]);
    $fee->forceFill(['status' => Contract::STATUS_APPROVED])->saveQuietly();

    return [$project, $viewer];
}

it('lands on the nearest upcoming project through the page filters', function () {
    [$project, $viewer] = dashboardProject();
    actingAs($viewer);

    Livewire::test(Dashboard::class)
        ->assertSet('filters.projectId', $project->id)
        ->assertSet('filters.type', Dashboard::ALL);
});

it('shows the picked project money in the two stat cards', function () {
    [$project, $viewer] = dashboardProject();
    actingAs($viewer);

    Livewire::test(ProjectStatsWidget::class, ['pageFilters' => ['projectId' => $project->id]])
        ->assertSee('10 000 000 UZS')
        ->assertSee(__('app.label.fees_total'));
});

it('carries the project name, dates and counts on the overview strip', function () {
    [$project, $viewer] = dashboardProject();
    actingAs($viewer);

    Livewire::test(ProjectOverviewWidget::class, ['pageFilters' => ['projectId' => $project->id]])
        ->assertSee('ATM 25')
        ->assertSee('Дубай')
        ->assertSee(__('app.label.participants'))
        ->assertSee(__('app.label.filters'));
});

it('asks to pick a project when the filters leave the selection empty', function () {
    [, $viewer] = dashboardProject();
    actingAs($viewer);

    Livewire::test(ProjectOverviewWidget::class, ['pageFilters' => ['projectId' => null]])
        ->assertSee(__('app.message.pulse_pick_project'))
        ->assertSee(__('app.label.filters'));
});

it('lists the project contracts with counterparties in the native table', function () {
    [$project, $viewer] = dashboardProject();

    // Someone else's contract in the same project must stay invisible.
    Contract::factory()->create([
        'project_id' => $project->id,
        'responsible_id' => User::factory()->create()->id,
        'number' => 'FOREIGN-77',
    ]);

    actingAs($viewer);

    Livewire::test(ProjectContractsTableWidget::class, ['pageFilters' => ['projectId' => $project->id]])
        ->assertCanSeeTableRecords(Contract::where('number', 'А-2')->get())
        ->assertCanNotSeeTableRecords(Contract::where('number', 'FOREIGN-77')->get())
        ->assertSee('ООО «ZAMIN TRAVEL»');
});

it('lists income counterparties with payment state in the participants table', function () {
    [$project, $viewer] = dashboardProject();
    actingAs($viewer);

    Livewire::test(ProjectParticipantsTableWidget::class, ['pageFilters' => ['projectId' => $project->id]])
        ->assertCanSeeTableRecords(Contract::where('number', 'А-2')->get())
        ->assertSee('ООО «ZAMIN TRAVEL»')
        ->assertSee(__('app.payment_status.not_paid'));
});

it('hides every dashboard project widget without the projects permission', function () {
    actingAs(User::factory()->create(['status' => User::STATUS_ACTIVE]));

    expect(ProjectStatsWidget::canView())->toBeFalse()
        ->and(ProjectOverviewWidget::canView())->toBeFalse()
        ->and(ProjectContractsTableWidget::canView())->toBeFalse()
        ->and(ProjectParticipantsTableWidget::canView())->toBeFalse();
});
