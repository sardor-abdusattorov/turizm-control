<?php

use App\Filament\Resources\Sponsors\Pages\ListSponsors;
use App\Filament\Widgets\Counterparty\CounterpartyProjectsTableWidget;
use App\Models\Contract;
use App\Models\Currency;
use App\Models\Project;
use App\Models\Sponsor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('aggregates a sponsor sponsorship contracts by currency', function () {
    $uzs = Currency::factory()->create(['short_name' => 'UZS']);
    $usd = Currency::factory()->create(['short_name' => 'USD']);
    $sponsor = Sponsor::factory()->create();
    $viewer = userWithPermission('view_any_sponsor', 'view_all_contracts');

    Contract::factory()->sponsorship()->create([
        'sponsor_id' => $sponsor->id,
        'currency_id' => $uzs->id,
        'amount' => 30_000_000,
        'paid_percent' => 50,
        'status' => Contract::STATUS_APPROVED,
    ]);
    Contract::factory()->sponsorship()->create([
        'sponsor_id' => $sponsor->id,
        'currency_id' => $uzs->id,
        'amount' => 20_000_000,
        'paid_percent' => 100,
        'status' => Contract::STATUS_APPROVED,
    ]);
    Contract::factory()->sponsorship()->create([
        'sponsor_id' => $sponsor->id,
        'currency_id' => $usd->id,
        'amount' => 5000,
        'paid_percent' => 0,
        'status' => Contract::STATUS_APPROVED,
    ]);

    $totals = $sponsor->projectTotalsByCurrency($viewer);

    expect($sponsor->sponsorshipContracts()->count())->toBe(3)
        ->and($totals)->toHaveCount(2);

    $uzsRow = $totals->firstWhere('currency', 'UZS');

    expect($uzsRow['count'])->toBe(2)
        ->and($uzsRow['total'])->toBe(50_000_000.0)
        ->and($uzsRow['paid'])->toBe(35_000_000.0);
});

it('renders the sponsor breakdown as a native Filament table widget', function () {
    $project = Project::factory()->create(['name' => 'BITF-2026 Bishkek', 'starts_on' => '2026-04-30']);
    $uzs = Currency::factory()->create(['short_name' => 'UZS']);
    $sponsor = Sponsor::factory()->create();
    actingAs(userWithPermission('view_any_sponsor', 'view_contract', 'view_all_contracts'));

    Contract::factory()->sponsorship()->create([
        'sponsor_id' => $sponsor->id,
        'project_id' => $project->id,
        'currency_id' => $uzs->id,
        'amount' => 38_000_000,
        'paid_percent' => 100,
        'status' => Contract::STATUS_APPROVED,
    ]);

    Livewire::test(CounterpartyProjectsTableWidget::class, ['sponsorId' => $sponsor->id])
        ->assertSuccessful()
        ->assertSee('BITF-2026 Bishkek')
        ->assertSee('UZS');
});

it('shows the empty state when the sponsor has no sponsorship contracts', function () {
    $sponsor = Sponsor::factory()->create();
    actingAs(userWithPermission('view_any_sponsor', 'view_contract', 'view_all_contracts'));

    Livewire::test(CounterpartyProjectsTableWidget::class, ['sponsorId' => $sponsor->id])
        ->assertSuccessful()
        ->assertSee(__('app.message.no_projects_for_contact'));
});

it('lists sponsors with the project-count badge without error', function () {
    $sponsor = Sponsor::factory()->create();
    Contract::factory()->sponsorship()->count(2)->create([
        'sponsor_id' => $sponsor->id,
        'status' => Contract::STATUS_APPROVED,
    ]);

    actingAs(userWithPermission('view_any_sponsor', 'view_all_contracts'));

    Livewire::test(ListSponsors::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$sponsor])
        ->assertTableColumnStateSet('sponsorship_contracts_count', 2, record: $sponsor);
});
