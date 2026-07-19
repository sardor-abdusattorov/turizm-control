<?php

use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Models\Contract;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');
});

function paymentForResponsible(User $responsible): Payment
{
    $contract = Contract::factory()->create([
        'status' => Contract::STATUS_APPROVED,
        'responsible_id' => $responsible->id,
    ]);

    return Payment::create([
        'contract_id' => $contract->id,
        'created_by' => $responsible->id,
        'percent' => 20,
        'paid_at' => now(),
        'screenshots' => ['payments/seed.png'],
    ]);
}

it('shows a manager only the payments on their own contracts', function () {
    $manager = userWithPermission('view_any_payment');
    $own = paymentForResponsible($manager);
    $foreign = paymentForResponsible(User::factory()->create());

    actingAs($manager);

    Livewire::test(ListPayments::class)
        ->assertCanSeeTableRecords([$own])
        ->assertCanNotSeeTableRecords([$foreign]);
});

it('shows every payment to a user granted view_all_contracts', function () {
    $viewer = userWithPermission('view_any_payment', 'view_all_contracts');
    $a = paymentForResponsible(User::factory()->create());
    $b = paymentForResponsible(User::factory()->create());

    actingAs($viewer);

    Livewire::test(ListPayments::class)
        ->assertCanSeeTableRecords([$a, $b]);
});
