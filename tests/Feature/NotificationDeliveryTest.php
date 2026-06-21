<?php

use App\Models\Contract;
use App\Models\ContractApprover;
use App\Models\Payment;
use App\Models\User;
use App\Services\Contracts\ContractWorkflow;
use App\Services\Payments\PaymentNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
});

it('notifies the responsible manager when someone else records a payment', function () {
    $manager = User::factory()->create();
    $accountant = User::factory()->create();
    $contract = Contract::factory()->approved()->create(['responsible_id' => $manager->id]);

    $payment = Payment::factory()->create([
        'contract_id' => $contract->id,
        'created_by' => $accountant->id,
        'percent' => 30,
    ]);

    app(PaymentNotifier::class)->notifyPaymentRecorded($payment);

    $notifications = $manager->fresh()->notifications;

    expect($notifications)->toHaveCount(1)
        ->and($notifications->first()->data['title'])->toBe(__('app.notification.payment_recorded.title'));
});

it('does not notify the manager about a payment they recorded themselves', function () {
    $manager = User::factory()->create();
    $contract = Contract::factory()->approved()->create(['responsible_id' => $manager->id]);

    $payment = Payment::factory()->create([
        'contract_id' => $contract->id,
        'created_by' => $manager->id,
        'percent' => 30,
    ]);

    app(PaymentNotifier::class)->notifyPaymentRecorded($payment);

    expect($manager->fresh()->notifications)->toHaveCount(0);
});

it('sends the fully-paid notification once the contract is paid in full', function () {
    $manager = User::factory()->create();
    $accountant = User::factory()->create();
    $contract = Contract::factory()->approved()->create(['responsible_id' => $manager->id]);

    $payment = Payment::factory()->create([
        'contract_id' => $contract->id,
        'created_by' => $accountant->id,
        'percent' => 100,
    ]);

    app(PaymentNotifier::class)->notifyPaymentRecorded($payment);

    expect($manager->fresh()->notifications->first()->data['title'])
        ->toBe(__('app.notification.payment_completed.title'));
});

it('database-notifies the first approver when a contract is submitted', function () {
    $responsible = User::factory()->create();
    $approver = User::factory()->create(['status' => User::STATUS_ACTIVE]);

    $contract = Contract::factory()->withDocument()->create([
        'responsible_id' => $responsible->id,
        'status' => Contract::STATUS_DRAFT,
    ]);
    ContractApprover::factory()->create([
        'contract_id' => $contract->id, 'user_id' => $approver->id, 'order' => 1,
    ]);

    actingAs($responsible);
    app(ContractWorkflow::class)->submit($contract);

    $notifications = $approver->fresh()->notifications;

    expect($notifications)->toHaveCount(1)
        ->and($notifications->first()->data['title'])->toBe(__('app.notification.approval_requested.title'));
});
