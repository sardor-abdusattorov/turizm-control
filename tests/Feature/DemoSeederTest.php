<?php

use App\Enums\ProjectType;
use App\Models\Contract;
use App\Models\ContractType;
use App\Models\Order;
use App\Models\Project;
use App\Models\Sponsor;
use Database\Seeders\ContractTypeSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('seeds only the project registries — no contracts or orders', function () {
    Storage::fake('local');

    $this->seed(DatabaseSeeder::class);

    expect(Contract::count())->toBe(0)
        ->and(Order::count())->toBe(1)
        ->and(Order::query()->value('number'))->toBe('49-AF');

    expect(Project::query()->where('type', ProjectType::International->value)->count())->toBe(30)
        ->and(Project::query()->where('type', ProjectType::Internal->value)->count())->toBe(5);
});

it('seeds FITUR-2025 as a shell from the registry — costs only, income entered by hand', function () {
    Storage::fake('local');

    $this->seed(DatabaseSeeder::class);

    $fitur = Project::query()->firstWhere('name', 'FITUR-2025');

    expect($fitur)->not->toBeNull()
        ->and($fitur->area_sqm)->not->toBeNull()
        ->and($fitur->contracts()->count())->toBe(0)
        ->and((float) $fitur->feesTotal())->toBe(0.0);
});

it('seeds the exhibition sponsors as standalone entities', function () {
    Storage::fake('local');

    $this->seed(DatabaseSeeder::class);

    expect(Sponsor::query()->where('name', 'Uzbekistan Airways')->exists())->toBeTrue()
        ->and(Sponsor::count())->toBeGreaterThanOrEqual(4);
});

it('the five filled local events carry their photo reports', function () {
    Storage::fake('local');

    $this->seed(DatabaseSeeder::class);

    expect(Project::query()->where('type', ProjectType::Internal->value)->count())->toBe(5)
        ->and(Project::query()->where('photo_report_url', 'https://clck.ru/3UYYzh')->exists())->toBeTrue();
});

it('re-seeding never duplicates the projects', function () {
    Storage::fake('local');

    $this->seed(DatabaseSeeder::class);
    $before = Project::count();

    $this->seed(DatabaseSeeder::class);

    expect(Project::count())->toBe($before);
});

it('seeds services as income and contractor services as expense', function () {
    $this->seed(ContractTypeSeeder::class);

    $direction = fn (string $ru) => ContractType::query()
        ->where('title->ru', $ru)->first()?->direction?->value;

    expect($direction('Оказание услуг'))->toBe('income')
        ->and($direction('Услуги подрядчика'))->toBe('expense')
        ->and($direction('Взнос участника'))->toBe('income');
});
