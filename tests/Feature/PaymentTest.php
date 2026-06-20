<?php

use App\Enums\PaymentStatus;
use App\Models\Contract;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
});

function paymentContract(array $overrides = []): Contract
{
    return Contract::factory()->create([
        'status' => Contract::STATUS_APPROVED,
        ...$overrides,
    ]);
}

it('marks a contract as partially paid after the first payment', function () {
    $contract = paymentContract();
    $user = User::factory()->create();

    Payment::create([
        'contract_id' => $contract->id,
        'created_by' => $user->id,
        'percent' => 30,
        'paid_at' => now(),
        'screenshot' => 'payments/test.png',
    ]);

    expect($contract->fresh()->payment_status)->toBe(PaymentStatus::PartiallyPaid)
        ->and((float) $contract->fresh()->paid_percent)->toBe(30.00);
});

it('marks a contract as fully paid when payments reach 100 percent', function () {
    $contract = paymentContract();
    $user = User::factory()->create();

    Payment::create([
        'contract_id' => $contract->id,
        'created_by' => $user->id,
        'percent' => 40,
        'paid_at' => now(),
        'screenshot' => 'payments/a.png',
    ]);

    Payment::create([
        'contract_id' => $contract->id,
        'created_by' => $user->id,
        'percent' => 60,
        'paid_at' => now(),
        'screenshot' => 'payments/b.png',
    ]);

    expect($contract->fresh()->payment_status)->toBe(PaymentStatus::FullyPaid)
        ->and((float) $contract->fresh()->paid_percent)->toBe(100.00);
});

it('falls back to not paid after every payment is deleted', function () {
    $contract = paymentContract();
    $user = User::factory()->create();

    $payment = Payment::create([
        'contract_id' => $contract->id,
        'created_by' => $user->id,
        'percent' => 25,
        'paid_at' => now(),
        'screenshot' => 'payments/x.png',
    ]);

    expect($contract->fresh()->payment_status)->toBe(PaymentStatus::PartiallyPaid);

    $payment->delete();

    expect($contract->fresh()->payment_status)->toBe(PaymentStatus::NotPaid)
        ->and((float) $contract->fresh()->paid_percent)->toBe(0.00);
});

it('deletes the screenshot file from disk when the payment is deleted', function () {
    $contract = paymentContract();
    $user = User::factory()->create();

    $path = UploadedFile::fake()->image('proof.png')->storeAs(Payment::SCREENSHOT_DIR, 'proof.png', 'public');

    $payment = Payment::create([
        'contract_id' => $contract->id,
        'created_by' => $user->id,
        'percent' => 10,
        'paid_at' => now(),
        'screenshot' => $path,
    ]);

    Storage::disk('public')->assertExists($path);

    $payment->delete();

    Storage::disk('public')->assertMissing($path);
});

it('cascades delete: removing a contract removes its payments', function () {
    $contract = paymentContract();
    $user = User::factory()->create();

    Payment::create([
        'contract_id' => $contract->id,
        'created_by' => $user->id,
        'percent' => 20,
        'paid_at' => now(),
        'screenshot' => 'payments/c.png',
    ]);

    $contract->delete();

    expect(Payment::query()->where('contract_id', $contract->id)->count())->toBe(0);
});

it('reports remaining percent and cannot accept payment once fully paid', function () {
    $contract = paymentContract();
    $user = User::factory()->create();

    Payment::create([
        'contract_id' => $contract->id,
        'created_by' => $user->id,
        'percent' => 80,
        'paid_at' => now(),
        'screenshot' => 'payments/big.png',
    ]);

    expect($contract->fresh()->remainingPercent())->toBe(20.0)
        ->and($contract->fresh()->canAcceptPayment())->toBeTrue();

    Payment::create([
        'contract_id' => $contract->id,
        'created_by' => $user->id,
        'percent' => 20,
        'paid_at' => now(),
        'screenshot' => 'payments/done.png',
    ]);

    expect($contract->fresh()->canAcceptPayment())->toBeFalse();
});

it('refuses to accept payment when the contract is not approved yet', function () {
    $contract = paymentContract(['status' => Contract::STATUS_DRAFT]);

    expect($contract->canAcceptPayment())->toBeFalse();
});
