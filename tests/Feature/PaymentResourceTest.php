<?php

use App\Filament\Resources\Payments\Pages\CreatePayment;
use App\Filament\Resources\Payments\Pages\EditPayment;
use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Models\Contract;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');
});

it('lists payments for users who can view any payment', function () {
    $user = userWithPermission('view_any_payment');
    actingAs($user);

    Livewire::test(ListPayments::class)->assertSuccessful();
});

it('forbids the create page for users without create_payment', function () {
    $user = userWithPermission('view_any_payment');
    actingAs($user);

    Livewire::test(CreatePayment::class)->assertForbidden();
});

it('records a payment when the form is submitted with valid data', function () {
    $user = userWithPermission('view_any_payment', 'create_payment', 'view_all_contracts');
    actingAs($user);

    $contract = Contract::factory()->create(['status' => Contract::STATUS_APPROVED]);

    Livewire::test(CreatePayment::class)
        ->fillForm([
            'contract_id' => $contract->id,
            'percent' => 35,
            'paid_at' => now()->toDateString(),
            'screenshot' => UploadedFile::fake()->image('proof.png'),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Payment::query()->where('contract_id', $contract->id)->count())->toBe(1)
        ->and((float) $contract->fresh()->paid_percent)->toBe(35.00);
});

it('refuses to accept a payment that would exceed the remaining percent', function () {
    $user = userWithPermission('view_any_payment', 'create_payment', 'view_all_contracts');
    actingAs($user);

    $contract = Contract::factory()->create(['status' => Contract::STATUS_APPROVED]);

    Payment::create([
        'contract_id' => $contract->id,
        'created_by' => $user->id,
        'percent' => 90,
        'paid_at' => now(),
        'screenshot' => 'payments/seed.png',
    ]);

    Livewire::test(CreatePayment::class)
        ->fillForm([
            'contract_id' => $contract->id,
            'percent' => 25,
            'paid_at' => now()->toDateString(),
            'screenshot' => UploadedFile::fake()->image('proof.png'),
        ])
        ->call('create')
        ->assertHasFormErrors(['percent']);
});

it('edits an existing payment and resyncs the contract paid percent', function () {
    $user = userWithPermission('view_any_payment', 'view_payment', 'update_payment', 'view_all_contracts');
    actingAs($user);

    $contract = Contract::factory()->create(['status' => Contract::STATUS_APPROVED]);
    $payment = Payment::create([
        'contract_id' => $contract->id,
        'created_by' => $user->id,
        'percent' => 30,
        'paid_at' => now(),
        'screenshot' => 'payments/seed.png',
    ]);

    expect((float) $contract->fresh()->paid_percent)->toBe(30.00);

    Livewire::test(EditPayment::class, ['record' => $payment->id])
        ->fillForm([
            'percent' => 55,
            'screenshot' => UploadedFile::fake()->image('updated.png'),
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect((float) $payment->fresh()->percent)->toBe(55.00)
        ->and((float) $contract->fresh()->paid_percent)->toBe(55.00);
});

it('forbids the edit page for users without update_payment', function () {
    // view_all_contracts lets them resolve the record (the resource hides
    // payments they can't see), so the 403 comes from the missing
    // update_payment ability, not a 404.
    $user = userWithPermission('view_any_payment', 'view_payment', 'view_all_contracts');
    actingAs($user);

    $contract = Contract::factory()->create(['status' => Contract::STATUS_APPROVED]);
    $payment = Payment::create([
        'contract_id' => $contract->id,
        'created_by' => $user->id,
        'percent' => 30,
        'paid_at' => now(),
        'screenshot' => 'payments/seed.png',
    ]);

    Livewire::test(EditPayment::class, ['record' => $payment->id])->assertForbidden();
});

it('refuses to record a payment against a contract that is not approved', function () {
    $user = userWithPermission('view_any_payment', 'create_payment');
    actingAs($user);

    $contract = Contract::factory()->create(['status' => Contract::STATUS_DRAFT]);

    Livewire::test(CreatePayment::class)
        ->fillForm([
            'contract_id' => $contract->id,
            'percent' => 10,
            'paid_at' => now()->toDateString(),
            'screenshot' => UploadedFile::fake()->image('proof.png'),
        ])
        ->call('create')
        ->assertHasFormErrors(['contract_id']);
});
