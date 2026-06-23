<?php

use App\Enums\ContractStatus;
use App\Enums\PaymentStatus;
use App\Models\Contract;
use App\Models\ContractApprover;
use App\Models\Payment;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('seeds a rich, file-backed demo dataset', function () {
    Storage::fake('local');

    $this->seed(DatabaseSeeder::class);

    // Plenty of contracts for a convincing showcase.
    expect(Contract::count())->toBeGreaterThanOrEqual(30);

    // Every workflow status is represented.
    $statuses = Contract::query()
        ->pluck('status')
        ->map(fn ($status) => $status instanceof BackedEnum ? $status->value : $status)
        ->unique()
        ->all();

    foreach (ContractStatus::cases() as $case) {
        expect($statuses)->toContain($case->value);
    }

    // Approved contracts span the full payment spectrum.
    expect(Contract::query()->where('status', ContractStatus::Approved->value)
        ->where('payment_status', PaymentStatus::FullyPaid->value)->exists())->toBeTrue()
        ->and(Contract::query()->where('status', ContractStatus::Approved->value)
            ->where('payment_status', PaymentStatus::PartiallyPaid->value)->exists())->toBeTrue()
        ->and(Contract::query()->where('status', ContractStatus::Approved->value)
            ->where('payment_status', PaymentStatus::NotPaid->value)->exists())->toBeTrue();

    // At least one in-review approval is overdue, for the SLA indicators.
    expect(ContractApprover::query()
        ->where('status', ContractApprover::STATUS_PENDING->value)
        ->whereNotNull('due_at')
        ->where('due_at', '<', now())
        ->exists())->toBeTrue();
});

it('writes real media files for users, payments and documents', function () {
    Storage::fake('local');

    $this->seed(DatabaseSeeder::class);

    $disk = Storage::disk('local');

    $user = User::query()->whereNotNull('avatar_url')->first();
    expect($user)->not->toBeNull();
    $disk->assertExists($user->avatar_url);

    $payment = Payment::query()->whereNotNull('screenshot')->first();
    expect($payment)->not->toBeNull();
    $disk->assertExists($payment->screenshot);

    $contract = Contract::query()->whereNotNull('document_file')->first();
    expect($contract)->not->toBeNull();
    $disk->assertExists($contract->document_file);
});
