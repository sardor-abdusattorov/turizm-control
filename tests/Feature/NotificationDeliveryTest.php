<?php

use App\Models\Contract;
use App\Models\ContractApprover;
use App\Models\Payment;
use App\Models\User;
use App\Services\Contracts\ContractWorkflow;
use App\Services\Payments\PaymentNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
});

it('notifies the manager AND the next approver when a step is approved', function () {
    $manager = User::factory()->create();
    $lawyer = asApprover(User::factory()->create(['status' => User::STATUS_ACTIVE]));
    $accountant = User::factory()->create(['status' => User::STATUS_ACTIVE]);

    $contract = Contract::factory()->withDocument()->create([
        'responsible_id' => $manager->id,
        'status' => Contract::STATUS_IN_REVIEW,
    ]);
    ContractApprover::factory()->create([
        'contract_id' => $contract->id, 'user_id' => $lawyer->id, 'order' => 1,
        'status' => ContractApprover::STATUS_PENDING,
    ]);
    ContractApprover::factory()->create([
        'contract_id' => $contract->id, 'user_id' => $accountant->id, 'order' => 2,
        'status' => ContractApprover::STATUS_QUEUED,
    ]);

    actingAs($lawyer);
    app(ContractWorkflow::class)->approve($contract->fresh(), $lawyer);

    // The manager hears that the step passed...
    expect($manager->fresh()->notifications->pluck('data.title'))
        ->toContain(__('app.notification.step_approved.title'));

    // ...and the next approver is asked to review.
    expect($accountant->fresh()->notifications->pluck('data.title'))
        ->toContain(__('app.notification.approval_requested.title'));
});

it('includes the approver, their decision time and comment in the step-approved notification', function () {
    $manager = User::factory()->create();
    $lawyer = asApprover(User::factory()->create(['status' => User::STATUS_ACTIVE, 'name' => 'Alisher Yuldoshev']));
    $accountant = User::factory()->create(['status' => User::STATUS_ACTIVE]);

    $contract = Contract::factory()->withDocument()->create([
        'responsible_id' => $manager->id,
        'status' => Contract::STATUS_IN_REVIEW,
    ]);
    ContractApprover::factory()->create([
        'contract_id' => $contract->id, 'user_id' => $lawyer->id, 'order' => 1,
        'status' => ContractApprover::STATUS_PENDING,
    ]);
    ContractApprover::factory()->create([
        'contract_id' => $contract->id, 'user_id' => $accountant->id, 'order' => 2,
        'status' => ContractApprover::STATUS_QUEUED,
    ]);

    actingAs($lawyer);
    app(ContractWorkflow::class)->approve($contract->fresh(), $lawyer, 'Сумма верная, согласовано');

    $body = $manager->fresh()->notifications
        ->firstWhere('data.title', __('app.notification.step_approved.title'))
        ->data['body'];

    expect($body)->toContain('Alisher Yuldoshev')
        ->and($body)->toContain('Сумма верная, согласовано')
        ->and($body)->toContain((string) $contract->number);
});

it('names the final approver and time in the fully-approved notification', function () {
    $manager = User::factory()->create();
    $approver = asApprover(User::factory()->create(['status' => User::STATUS_ACTIVE, 'name' => 'Madina Saidova']));

    $contract = Contract::factory()->withDocument()->create([
        'responsible_id' => $manager->id,
        'status' => Contract::STATUS_IN_REVIEW,
    ]);
    ContractApprover::factory()->create([
        'contract_id' => $contract->id, 'user_id' => $approver->id, 'order' => 1,
        'status' => ContractApprover::STATUS_PENDING,
    ]);

    actingAs($approver);
    app(ContractWorkflow::class)->approve($contract->fresh(), $approver);

    $body = $manager->fresh()->notifications
        ->firstWhere('data.title', __('app.notification.contract_approved.title'))
        ->data['body'];

    expect($body)->toContain('Madina Saidova')
        ->and($body)->toContain((string) $contract->number);
});

it('sends the Telegram step-approved message in the recipient locale, not the sender panel locale', function () {
    config(['services.telegram.bot_token' => 'test-token']);
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

    $manager = User::factory()->withTelegram('600', 'ru')->create();
    $lawyer = asApprover(User::factory()->create(['status' => User::STATUS_ACTIVE, 'name' => 'Alisher Yuldoshev']));
    $accountant = User::factory()->create(['status' => User::STATUS_ACTIVE]);

    $contract = Contract::factory()->withDocument()->create([
        'responsible_id' => $manager->id,
        'status' => Contract::STATUS_IN_REVIEW,
    ]);
    ContractApprover::factory()->create([
        'contract_id' => $contract->id, 'user_id' => $lawyer->id, 'order' => 1,
        'status' => ContractApprover::STATUS_PENDING,
    ]);
    ContractApprover::factory()->create([
        'contract_id' => $contract->id, 'user_id' => $accountant->id, 'order' => 2,
        'status' => ContractApprover::STATUS_QUEUED,
    ]);

    // Lawyer is browsing the panel in English — that must NOT leak into the
    // Telegram message that goes to the manager (who picked Russian in the bot).
    App::setLocale('en');
    actingAs($lawyer);
    app(ContractWorkflow::class)->approve($contract->fresh(), $lawyer, 'окей');

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        $text = $request['text'] ?? '';

        return str_contains($text, 'Этап согласования пройден')
            && ! str_contains($text, 'Approval step passed');
    });

    expect(App::getLocale())->toBe('en');
});

it('names the sender in the approval-requested notification', function () {
    $manager = User::factory()->create(['name' => 'Sardor Abdusattorov']);
    $approver = User::factory()->create(['status' => User::STATUS_ACTIVE]);

    $contract = Contract::factory()->withDocument()->create([
        'responsible_id' => $manager->id,
        'status' => Contract::STATUS_DRAFT,
    ]);
    ContractApprover::factory()->create([
        'contract_id' => $contract->id, 'user_id' => $approver->id, 'order' => 1,
    ]);

    actingAs($manager);
    app(ContractWorkflow::class)->submit($contract);

    expect($approver->fresh()->notifications->first()->data['body'])
        ->toContain('Sardor Abdusattorov');
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
