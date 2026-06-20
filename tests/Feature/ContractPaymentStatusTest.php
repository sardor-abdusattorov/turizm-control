<?php

use App\Enums\PaymentStatus;
use App\Models\Contract;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('starts as not paid for a brand new contract', function () {
    $contract = Contract::factory()->create(['status' => Contract::STATUS_APPROVED]);

    expect($contract->payment_status)->toBe(PaymentStatus::NotPaid)
        ->and((float) $contract->paid_percent)->toBe(0.00);
});

it('transitions through partially paid back to not paid as payments are removed', function () {
    $contract = Contract::factory()->create(['status' => Contract::STATUS_APPROVED]);
    $user = User::factory()->create();

    $first = Payment::create([
        'contract_id' => $contract->id,
        'created_by' => $user->id,
        'percent' => 40,
        'paid_at' => now(),
        'screenshot' => 'payments/1.png',
    ]);

    expect($contract->fresh()->payment_status)->toBe(PaymentStatus::PartiallyPaid);

    $second = Payment::create([
        'contract_id' => $contract->id,
        'created_by' => $user->id,
        'percent' => 60,
        'paid_at' => now(),
        'screenshot' => 'payments/2.png',
    ]);

    expect($contract->fresh()->payment_status)->toBe(PaymentStatus::FullyPaid);

    $second->delete();
    expect($contract->fresh()->payment_status)->toBe(PaymentStatus::PartiallyPaid);

    $first->delete();
    expect($contract->fresh()->payment_status)->toBe(PaymentStatus::NotPaid);
});

it('moves the cached total when a payment is reassigned between contracts', function () {
    $source = Contract::factory()->create(['status' => Contract::STATUS_APPROVED]);
    $target = Contract::factory()->create(['status' => Contract::STATUS_APPROVED]);
    $user = User::factory()->create();

    $payment = Payment::create([
        'contract_id' => $source->id,
        'created_by' => $user->id,
        'percent' => 30,
        'paid_at' => now(),
        'screenshot' => 'payments/move.png',
    ]);

    expect((float) $source->fresh()->paid_percent)->toBe(30.00)
        ->and((float) $target->fresh()->paid_percent)->toBe(0.00);

    $payment->update(['contract_id' => $target->id]);

    expect((float) $source->fresh()->paid_percent)->toBe(0.00)
        ->and((float) $target->fresh()->paid_percent)->toBe(30.00)
        ->and($source->fresh()->payment_status)->toBe(PaymentStatus::NotPaid)
        ->and($target->fresh()->payment_status)->toBe(PaymentStatus::PartiallyPaid);
});
