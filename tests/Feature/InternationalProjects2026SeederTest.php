<?php

use App\Enums\ProjectType;
use App\Models\Project;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\InternationalProjects2026Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function seed2026Registry(): void
{
    test()->seed(CurrencySeeder::class);
    test()->seed(InternationalProjects2026Seeder::class);
}

it('imports the 2026 international exhibition shells with venues', function () {
    seed2026Registry();

    // Shells only — venue/dates/costs; participation income is entered by hand.
    expect(Project::count())->toBe(14);

    $fitur = Project::where('name', 'like', 'FITUR-2026%')->firstOrFail();
    expect($fitur->type)->toBe(ProjectType::International)
        ->and($fitur->venue)->toBe('Madrid, Spain')
        ->and($fitur->starts_on->toDateString())->toBe('2026-01-21')
        ->and($fitur->ends_on->toDateString())->toBe('2026-01-25')
        ->and($fitur->areaCurrency?->short_name)->toBe('EUR')
        ->and($fitur->standCurrency?->short_name)->toBe('UZS')
        ->and($fitur->contracts()->count())->toBe(0);

    // KITF is priced in tenge — the currency added to the seeder for it.
    // The registry name carries literal quotes («"KITF-2026"»), so match loosely.
    $kitf = Project::where('name', 'like', '%KITF-2026%')->firstOrFail();
    expect($kitf->venue)->toBe('Алматы, Казахстан');
});

it('is idempotent — re-seeding never duplicates the projects', function () {
    seed2026Registry();
    test()->seed(InternationalProjects2026Seeder::class);

    expect(Project::count())->toBe(14);
});
