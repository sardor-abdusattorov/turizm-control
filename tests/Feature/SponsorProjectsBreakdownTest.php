<?php

use App\Enums\ParticipantRole;
use App\Filament\Resources\Sponsors\Pages\ListSponsors;
use App\Models\Currency;
use App\Models\Project;
use App\Models\ProjectParticipant;
use App\Models\Sponsor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('aggregates a sponsor project participations by currency', function () {
    $uzs = Currency::factory()->create(['short_name' => 'UZS']);
    $usd = Currency::factory()->create(['short_name' => 'USD']);
    $sponsor = Sponsor::factory()->create();

    ProjectParticipant::factory()->create(['sponsor_id' => $sponsor->id, 'role' => ParticipantRole::Sponsor, 'currency_id' => $uzs->id, 'amount' => 30_000_000, 'paid_amount' => 10_000_000]);
    ProjectParticipant::factory()->create(['sponsor_id' => $sponsor->id, 'role' => ParticipantRole::Sponsor, 'currency_id' => $uzs->id, 'amount' => 20_000_000, 'paid_amount' => 20_000_000]);
    ProjectParticipant::factory()->create(['sponsor_id' => $sponsor->id, 'role' => ParticipantRole::Sponsor, 'currency_id' => $usd->id, 'amount' => 5000, 'paid_amount' => 0]);

    $totals = $sponsor->projectTotalsByCurrency();

    expect($sponsor->participations()->count())->toBe(3)
        ->and($totals)->toHaveCount(2);

    $uzsRow = $totals->firstWhere('currency', 'UZS');

    expect($uzsRow['count'])->toBe(2)
        ->and($uzsRow['total'])->toBe(50_000_000.0)
        ->and($uzsRow['paid'])->toBe(30_000_000.0);
});

it('renders the breakdown view with the project, its date and currency', function () {
    $project = Project::factory()->create(['name' => 'BITF-2026 Bishkek', 'starts_on' => '2026-04-30']);
    $uzs = Currency::factory()->create(['short_name' => 'UZS']);
    $sponsor = Sponsor::factory()->create();

    ProjectParticipant::factory()->create([
        'sponsor_id' => $sponsor->id,
        'project_id' => $project->id,
        'role' => ParticipantRole::Sponsor,
        'currency_id' => $uzs->id,
        'amount' => 38_000_000,
        'paid_amount' => 38_000_000,
    ]);

    $html = view('filament.resources.sponsors.tables.projects-breakdown', [
        'participations' => $sponsor->participations()->with(['project', 'currency'])->get(),
        'totals' => $sponsor->projectTotalsByCurrency(),
    ])->render();

    expect($html)
        ->toContain('BITF-2026 Bishkek')
        ->toContain('30.04.2026')
        ->toContain('UZS');
});

it('shows the empty note when the sponsor has no participations', function () {
    $sponsor = Sponsor::factory()->create();

    $html = view('filament.resources.sponsors.tables.projects-breakdown', [
        'participations' => $sponsor->participations()->with(['project', 'currency'])->get(),
        'totals' => $sponsor->projectTotalsByCurrency(),
    ])->render();

    expect($html)->toContain(__('app.message.no_projects_for_sponsor'));
});

it('lists sponsors with the project-count badge without error', function () {
    $sponsor = Sponsor::factory()->create();
    ProjectParticipant::factory()->count(2)->create([
        'sponsor_id' => $sponsor->id,
        'role' => ParticipantRole::Sponsor,
    ]);

    actingAs(userWithPermission('view_any_sponsor'));

    Livewire::test(ListSponsors::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$sponsor]);
});
