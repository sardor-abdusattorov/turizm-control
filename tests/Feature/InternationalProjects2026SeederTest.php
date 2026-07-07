<?php

use App\Enums\ParticipantRole;
use App\Enums\ProjectType;
use App\Models\Contact;
use App\Models\Project;
use App\Models\ProjectParticipant;
use App\Models\Sponsor;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\InternationalProjects2026Seeder;
use Database\Seeders\RealTourAgentsSeeder;
use Database\Seeders\SponsorsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function seed2026Registry(): void
{
    test()->seed(CurrencySeeder::class);
    test()->seed(RealTourAgentsSeeder::class);
    test()->seed(SponsorsSeeder::class);
    test()->seed(InternationalProjects2026Seeder::class);
}

it('imports the 2026 international exhibitions registry with venues', function () {
    seed2026Registry();

    expect(Project::count())->toBe(14)
        ->and(ProjectParticipant::count())->toBe(94);

    $fitur = Project::where('name', 'like', 'FITUR-2026%')->firstOrFail();
    expect($fitur->type)->toBe(ProjectType::International)
        ->and($fitur->venue)->toBe('Madrid, Spain')
        ->and($fitur->starts_on->toDateString())->toBe('2026-01-21')
        ->and($fitur->ends_on->toDateString())->toBe('2026-01-25')
        ->and($fitur->areaCurrency?->short_name)->toBe('EUR')
        ->and($fitur->standCurrency?->short_name)->toBe('UZS');

    // The source file's BITF «Итог» cell says 32 000 000, but its own rows
    // sum to 40 000 000 (5 × 8 000 000) — the rows win.
    $bitf = Project::where('name', 'BITF-2026')->firstOrFail();
    expect($bitf->feesTotal())->toBe(40000000.0);
});

it('marks airways rows as sponsor contributions linked to the sponsors registry', function () {
    seed2026Registry();

    $sponsorRows = ProjectParticipant::query()
        ->where('role', ParticipantRole::Sponsor->value)
        ->get();

    $airways = Sponsor::query()->where('name', 'Uzbekistan Airways')->firstOrFail();

    expect($sponsorRows)->toHaveCount(6)
        ->and($sponsorRows->pluck('sponsor_id')->unique()->all())->toBe([$airways->id])
        ->and($sponsorRows->pluck('contact_id')->filter())->toBeEmpty()
        ->and((float) $sponsorRows->sum('amount'))->toBe(510000000.0);

    // The director's example: the FITUR sponsor row is the 100M airways one.
    $fitur = Project::where('name', 'like', 'FITUR-2026%')->firstOrFail();
    $fiturSponsor = $fitur->participants()->where('role', ParticipantRole::Sponsor->value)->firstOrFail();
    expect((float) $fiturSponsor->amount)->toBe(100000000.0)
        ->and($fiturSponsor->sponsor_id)->toBe($airways->id);

    // The seeder reuses the registry sponsor instead of duplicating it.
    expect(Sponsor::query()->where('name', 'Uzbekistan Airways')->count())->toBe(1);
});

it('links registry spellings to the real tour agents directory', function () {
    seed2026Registry();

    $orientStar = Contact::query()->where('name->ru', 'Orient Star Group')->firstOrFail();
    $zamin = Contact::query()->where('name->ru', 'Zamin DMC')->firstOrFail();

    // «OOO "Premium Trans"/Orient Star» is Orient Star Group's registry alias.
    $premiumTransRows = ProjectParticipant::query()->where('name', 'like', '%Premium Trans%')->get();
    expect($premiumTransRows)->not->toBeEmpty()
        ->and($premiumTransRows->pluck('contact_id')->unique()->all())->toBe([$orientStar->id]);

    expect(ProjectParticipant::query()->where('name', 'Zamin DMC')->firstOrFail()->contact_id)
        ->toBe($zamin->id);

    // Unknown one-off spellings stay unlinked but keep their name snapshot.
    expect(ProjectParticipant::query()->where('name', 'OOO "AVRUD"')->firstOrFail()->contact_id)
        ->toBeNull();
});

it('is idempotent — re-seeding never duplicates projects or participants', function () {
    seed2026Registry();
    test()->seed(InternationalProjects2026Seeder::class);

    expect(Project::count())->toBe(14)
        ->and(ProjectParticipant::count())->toBe(94);
});
