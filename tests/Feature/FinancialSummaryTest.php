<?php

use App\Models\Contract;
use App\Models\Currency;
use App\Models\User;
use App\Services\Dashboard\FinancialSummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('aggregates approved value, collected and outstanding in UZS', function () {
    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate('super_admin', 'web'));
    actingAs($admin->fresh());

    $currency = Currency::factory()->create(['value' => 1000]); // 1 unit = 1000 UZS

    // 1000 * 1000 = 1,000,000 UZS, half paid.
    Contract::factory()->create([
        'status' => Contract::STATUS_APPROVED,
        'currency_id' => $currency->id,
        'amount' => 1000,
        'paid_percent' => 50,
    ]);

    // 500 * 1000 = 500,000 UZS, nothing paid.
    Contract::factory()->create([
        'status' => Contract::STATUS_APPROVED,
        'currency_id' => $currency->id,
        'amount' => 500,
        'paid_percent' => 0,
    ]);

    // A draft must not count toward the totals.
    Contract::factory()->create([
        'status' => Contract::STATUS_DRAFT,
        'currency_id' => $currency->id,
        'amount' => 9999,
    ]);

    $totals = app(FinancialSummary::class)->totals();

    expect($totals['approved'])->toBe(1_500_000.0)
        ->and($totals['collected'])->toBe(500_000.0)
        ->and($totals['outstanding'])->toBe(1_000_000.0);
});

it('returns zeroes when there are no approved contracts', function () {
    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate('super_admin', 'web'));
    actingAs($admin->fresh());

    expect(app(FinancialSummary::class)->totals())
        ->toBe(['approved' => 0.0, 'collected' => 0.0, 'outstanding' => 0.0]);
});
