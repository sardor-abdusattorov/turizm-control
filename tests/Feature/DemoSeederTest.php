<?php

use App\Enums\ParticipantRole;
use App\Enums\ProjectType;
use App\Models\Contract;
use App\Models\Order;
use App\Models\Project;
use App\Models\ProjectParticipant;
use App\Models\Sponsor;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('seeds only the project registries — no contracts or orders', function () {
    Storage::fake('local');

    $this->seed(DatabaseSeeder::class);

    // The main seeder is deliberately lean now: reference data, accounts and
    // the projects. Contracts, orders and counterparties are entered by hand.
    expect(Contract::count())->toBe(0)
        ->and(Order::count())->toBe(0);

    // 16 international exhibitions (2025) + 14 (2026) + 5 local events.
    expect(Project::query()->where('type', ProjectType::International->value)->count())->toBe(30)
        ->and(Project::query()->where('type', ProjectType::Internal->value)->count())->toBe(5);
});

it('fills FITUR-2025 with its participant fees straight from the registry', function () {
    Storage::fake('local');

    $this->seed(DatabaseSeeder::class);

    $fitur = Project::query()->firstWhere('name', 'FITUR-2025');

    expect($fitur)->not->toBeNull()
        ->and($fitur->area_sqm)->not->toBeNull();

    // 11 tour operators × 38 000 000 сум = 418 000 000 (the registry «profit»).
    $participants = $fitur->participants()->where('role', ParticipantRole::Participant->value)->get();

    expect($participants->count())->toBe(11)
        ->and((float) $fitur->feesTotal())->toBe(418_000_000.0);
});

it('files Uzbekistan Airways rows as sponsor participations', function () {
    Storage::fake('local');

    $this->seed(DatabaseSeeder::class);

    // Airways rows are sponsor contributions, not participation fees — the
    // sponsor is auto-created by the registry linker.
    expect(Sponsor::query()->where('name', 'like', '%Uzbekistan Airways%')->exists())->toBeTrue();

    $sponsorParticipations = ProjectParticipant::query()
        ->where('role', ParticipantRole::Sponsor->value)
        ->count();

    expect($sponsorParticipations)->toBeGreaterThan(0);
});

it('the five filled local events carry their estimates and photo reports', function () {
    Storage::fake('local');

    $this->seed(DatabaseSeeder::class);

    expect(Project::query()->where('type', ProjectType::Internal->value)->count())->toBe(5)
        ->and(Project::query()->firstWhere('attendees_count', 165)?->photo_report_url)->toBe('https://clck.ru/3UYYzh');
});

it('re-seeding never duplicates the projects', function () {
    Storage::fake('local');

    $this->seed(DatabaseSeeder::class);
    $before = Project::count();

    $this->seed(DatabaseSeeder::class);

    expect(Project::count())->toBe($before);
});
