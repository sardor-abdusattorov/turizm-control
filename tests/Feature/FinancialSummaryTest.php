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
        ->and($totals['paid'])->toBe(500_000.0)
        ->and($totals['remaining'])->toBe(1_000_000.0);
});

it('aggregates without a column-ambiguity error for a non-oversight finance role', function () {
    // An accountant is not an oversight role, so visibleTo() adds a `status`
    // filter — which, joined against currencies (also has `status`), used to
    // throw "ambiguous column". This pins the table-qualified fix.
    $accountant = User::factory()->create();
    $accountant->assignRole(Role::findOrCreate('accountant', 'web'));
    actingAs($accountant->fresh());

    $currency = Currency::factory()->create(['value' => 1000]);

    Contract::factory()->create([
        'status' => Contract::STATUS_APPROVED,
        'currency_id' => $currency->id,
        'responsible_id' => $accountant->id,
        'amount' => 1000,
        'paid_percent' => 25,
    ]);

    $totals = app(FinancialSummary::class)->totals();

    expect($totals['approved'])->toBe(1_000_000.0)
        ->and($totals['paid'])->toBe(250_000.0)
        ->and($totals['remaining'])->toBe(750_000.0);
});

it('returns zeroes when there are no approved contracts', function () {
    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate('super_admin', 'web'));
    actingAs($admin->fresh());

    expect(app(FinancialSummary::class)->totals())
        ->toBe(['approved' => 0.0, 'paid' => 0.0, 'remaining' => 0.0]);
});
