<?php

use App\Models\Currency;
use App\Models\ProjectParticipant;
use Database\Seeders\CurrencySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds the five core currencies', function () {
    $this->seed(CurrencySeeder::class);

    expect(Currency::count())->toBe(5)
        ->and(Currency::pluck('short_name')->all())->toContain('UZS', 'USD', 'EUR', 'RUB', 'GBP')
        ->and(Currency::where('status', false)->count())->toBe(0);

    // Re-seeding must not duplicate anything.
    $this->seed(CurrencySeeder::class);
    expect(Currency::count())->toBe(5);
});

it('prunes retired unreferenced currencies but keeps referenced ones', function () {
    // Simulate the earlier over-seeded install: one retired code unreferenced,
    // one referenced by a participant row.
    $krw = Currency::factory()->create(['short_name' => 'KRW']);
    Currency::factory()->create(['short_name' => 'AED']);
    ProjectParticipant::factory()->create(['currency_id' => $krw->id]);

    $this->seed(CurrencySeeder::class);

    expect(Currency::where('short_name', 'KRW')->exists())->toBeTrue()
        ->and(Currency::where('short_name', 'AED')->exists())->toBeFalse();
});
