<?php

use App\Enums\ProjectType;
use App\Models\Project;
use App\Models\ProjectParticipant;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\Exhibitions2025Seeder;
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

it('is idempotent — re-seeding never duplicates projects or participants', function () {
    $this->seed(CurrencySeeder::class);
    $this->seed(Exhibitions2025Seeder::class);
    $this->seed(Exhibitions2025Seeder::class);

    expect(Project::count())->toBe(16)
        ->and(ProjectParticipant::count())->toBe(131);
});
