<?php

use App\Enums\ParticipantRole;
use App\Enums\ProjectType;
use App\Models\Contact;
use App\Models\Project;
use App\Models\ProjectParticipant;
use App\Models\Sponsor;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\Exhibitions2025Seeder;
use Database\Seeders\RealTourAgentsSeeder;
use Database\Seeders\SponsorsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('imports the 2025 international exhibitions registry', function () {
    $this->seed(CurrencySeeder::class);
    $this->seed(Exhibitions2025Seeder::class);

    expect(Project::count())->toBe(16)
        ->and(ProjectParticipant::count())->toBe(131)
        ->and((float) ProjectParticipant::query()->sum('amount'))->toBe(3692360000.0);

    $fitur = Project::where('name', 'FITUR-2025')->firstOrFail();
    expect($fitur->type)->toBe(ProjectType::International)
        ->and($fitur->starts_on->toDateString())->toBe('2025-01-22')
        ->and($fitur->ends_on->toDateString())->toBe('2025-01-26')
        ->and($fitur->areaCurrency?->short_name)->toBe('EUR')
        ->and($fitur->feesTotal())->toBe(418000000.0);

    // WTM is priced in pounds — the currency added to the seeder for it.
    $wtm = Project::where('name', 'World Travel Market-2025')->firstOrFail();
    expect($wtm->areaCurrency?->short_name)->toBe('GBP')
        ->and($wtm->standCurrency?->short_name)->toBe('GBP');

    // SCITE had a free exhibition area.
    $scite = Project::where('name', 'SCITE Sichuan 2025')->firstOrFail();
    expect($scite->area_is_free)->toBeTrue()
        ->and($scite->area_cost)->toBeNull();
});

it('links airways rows to the sponsor and registry spellings to contacts', function () {
    $this->seed(CurrencySeeder::class);
    $this->seed(RealTourAgentsSeeder::class);
    $this->seed(SponsorsSeeder::class);
    $this->seed(Exhibitions2025Seeder::class);

    $airways = Sponsor::query()->where('name', 'Uzbekistan Airways')->firstOrFail();
    $sponsorRows = ProjectParticipant::query()
        ->where('role', ParticipantRole::Sponsor->value)
        ->get();

    expect($sponsorRows)->toHaveCount(8)
        ->and($sponsorRows->pluck('sponsor_id')->unique()->all())->toBe([$airways->id])
        ->and(Sponsor::query()->where('name', 'Uzbekistan Airways')->count())->toBe(1);

    // The registry's «Oriet Star» typo still lands on Orient Star Group.
    $orientStar = Contact::query()->where('name->ru', 'Orient Star Group')->firstOrFail();
    $orietRows = ProjectParticipant::query()->where('name', 'OOO "Oriet Star Group"')->get();
    expect($orietRows)->not->toBeEmpty()
        ->and($orietRows->pluck('contact_id')->unique()->all())->toBe([$orientStar->id]);
});

it('is idempotent — re-seeding never duplicates projects or participants', function () {
    $this->seed(CurrencySeeder::class);
    $this->seed(Exhibitions2025Seeder::class);
    $this->seed(Exhibitions2025Seeder::class);

    expect(Project::count())->toBe(16)
        ->and(ProjectParticipant::count())->toBe(131);
});
