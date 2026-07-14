<?php

use App\Enums\ContractStatus;
use App\Enums\ProjectType;
use App\Models\Contact;
use App\Models\Contract;
use App\Models\Project;
use App\Models\Sponsor;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('seeds the real 2025 paper trail: 26 dossier contracts under their buyruqs', function () {
    Storage::fake('local');

    $this->seed(DatabaseSeeder::class);

    // Exactly the 26 scanned dossiers — no demo filler — all filed as signed.
    expect(Contract::count())->toBe(26)
        ->and(Contract::query()->where('status', ContractStatus::Approved->value)->count())->toBe(26);

    // Spot-check против скана: IFEMA, 16 595,41 EUR, приказ 06-АФ, FITUR.
    $ifema = Contract::query()->firstWhere('number', '2/FITUR 2025 Space');

    expect($ifema)->not->toBeNull()
        ->and((float) $ifema->amount)->toBe(16595.41)
        ->and($ifema->currency->short_name)->toBe('EUR')
        ->and($ifema->order?->number)->toBe('06-АФ')
        ->and($ifema->contact->getTranslation('name', 'ru'))->toBe('IFEMA Madrid')
        ->and($ifema->project?->name)->toContain('FITUR');

    // The annual 74-АФ really is the master order of the year.
    expect(Contract::query()->whereHas('order', fn ($q) => $q->where('number', '74-АФ'))->count())->toBe(18);

    // The five filled local events from the 2026 registry.
    expect(Project::query()->where('type', ProjectType::Internal->value)->count())->toBe(5)
        ->and(Project::query()->firstWhere('attendees_count', 165)?->photo_report_url)->toBe('https://clck.ru/3UYYzh');

    // Re-seeding must not duplicate anything.
    $this->seed(DatabaseSeeder::class);

    expect(Contract::count())->toBe(26);
});

it('seeds the real directories: contractors, tour operators and sponsors', function () {
    Storage::fake('local');

    $this->seed(DatabaseSeeder::class);

    // Foreign contractors straight from the contract requisites.
    foreach (['IFEMA Madrid', 'Think Strawberries MENA LLC', 'RX France S.A.S.', 'PTAK Warsaw Expo Sp. z o.o.'] as $name) {
        expect(Contact::query()->where('name->ru', $name)->exists())
            ->toBeTrue("contractor {$name} is missing");
    }

    // Tour operators from the participants registry.
    expect(Contact::query()->where('name->ru', 'Orient Star Group')->exists())->toBeTrue();

    // Sponsors (Uzbekistan Airways contributes as a sponsor, not a participant).
    expect(Sponsor::query()->where('name', 'like', '%Uzbekistan Airways%')->exists())->toBeTrue();
});
