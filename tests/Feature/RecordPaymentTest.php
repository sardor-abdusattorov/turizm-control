<?php

use App\Models\Contract;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payments\RecordPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;

uses(RefreshDatabase::class);

it('records a payment against an approved contract', function () {
    actingAs(User::factory()->create());

    $contract = Contract::factory()->create(['status' => Contract::STATUS_APPROVED]);

    $payment = app(RecordPayment::class)->record($contract, [
        'percent' => 30,
        'paid_at' => now(),
        'screenshot' => 'payments/test.png',
    ]);

    expect($payment)->toBeInstanceOf(Payment::class);
    assertDatabaseHas('payments', ['contract_id' => $contract->id, 'percent' => 30]);
});

it('refuses to record against a contract that cannot accept payment', function () {
    actingAs(User::factory()->create());

    $contract = Contract::factory()->create(['status' => Contract::STATUS_DRAFT]);

    $result = app(RecordPayment::class)->record($contract, [
        'percent' => 10,
        'paid_at' => now(),
        'screenshot' => 'payments/test.png',
    ]);

    expect($result)->toBeNull();
    assertDatabaseCount('payments', 0);
});
