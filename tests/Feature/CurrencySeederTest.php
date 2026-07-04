<?php

use App\Models\Currency;
use Database\Seeders\CurrencySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds the full exhibition-geography currency list', function () {
    $this->seed(CurrencySeeder::class);

    expect(Currency::count())->toBe(15)
        ->and(Currency::pluck('short_name')->all())->toContain(
            'UZS', 'USD', 'EUR', 'RUB', 'GBP',
            'CNY', 'AED', 'JPY', 'KRW', 'INR',
            'MYR', 'PLN', 'TRY', 'KZT', 'AZN',
        )
        ->and(Currency::where('status', false)->count())->toBe(0);

    // Re-seeding must not duplicate anything.
    $this->seed(CurrencySeeder::class);
    expect(Currency::count())->toBe(15);
});
