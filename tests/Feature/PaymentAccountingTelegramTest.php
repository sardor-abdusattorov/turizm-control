<?php

use App\Jobs\SendTelegramPhoto;
use App\Models\Contract;
use App\Models\Department;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payments\PaymentNotifier;
use App\Services\Telegram\TelegramService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.telegram.bot_token' => 'test-token',
        'services.telegram.bot_username' => 'turizm_bot',
    ]);

    Http::fake(['*' => Http::response(['ok' => true])]);
    Storage::fake('local');
});

function accountingUser(string $chatId): User
{
    $department = Department::query()->where('code', 'accounting')->first()
        ?? Department::factory()->create(['code' => 'accounting']);

    return User::factory()->withTelegram($chatId)->create([
        'status' => User::STATUS_ACTIVE,
        'department_id' => $department->id,
    ]);
}

function paymentWithScreenshot(Contract $contract, User $creator): Payment
{
    Storage::disk('local')->put('payments/shot.png', 'png');

    return Payment::query()->create([
        'contract_id' => $contract->id,
        'created_by' => $creator->id,
        'percent' => 40,
        'paid_at' => now()->toDateString(),
        'screenshot' => 'payments/shot.png',
    ]);
}

it('sends the payment screenshot to the accounting department', function () {
    Queue::fake();

    $accountant = accountingUser('7001');
    $manager = User::factory()->withTelegram('7002')->create();
    $creator = User::factory()->create();

    $contract = Contract::factory()->create(['responsible_id' => $manager->id]);
    $payment = paymentWithScreenshot($contract, $creator);

    app(PaymentNotifier::class)->notifyPaymentRecorded($payment);

    Queue::assertPushed(SendTelegramPhoto::class, function (SendTelegramPhoto $job) use ($contract) {
        return $job->chatId === '7001'
            && $job->path === 'payments/shot.png'
            && str_contains($job->caption, (string) $contract->number);
    });
});

it('never sends the screenshot to the person who recorded the payment', function () {
    Queue::fake();

    $accountant = accountingUser('7003');

    $contract = Contract::factory()->create();
    $payment = paymentWithScreenshot($contract, $accountant);

    app(PaymentNotifier::class)->notifyPaymentRecorded($payment);

    Queue::assertNotPushed(SendTelegramPhoto::class);
});

it('skips accountants without a linked telegram chat', function () {
    Queue::fake();

    $department = Department::factory()->create(['code' => 'accounting']);
    User::factory()->create(['status' => User::STATUS_ACTIVE, 'department_id' => $department->id]);

    $contract = Contract::factory()->create();
    $payment = paymentWithScreenshot($contract, User::factory()->create());

    app(PaymentNotifier::class)->notifyPaymentRecorded($payment);

    Queue::assertNotPushed(SendTelegramPhoto::class);
});

it('sendPhoto falls back to a text message when the file is gone', function () {
    app(TelegramService::class)->sendPhoto('55', 'payments/missing.png', 'caption text');

    Http::assertSent(fn ($request) => str_contains($request->url(), '/sendMessage')
        && ($request['text'] ?? null) === 'caption text');
});

it('sendPhoto uploads the file as multipart when it exists', function () {
    Storage::disk('local')->put('payments/real.png', 'png-bytes');

    app(TelegramService::class)->sendPhoto('55', 'payments/real.png', 'caption');

    Http::assertSent(fn ($request) => str_contains($request->url(), '/sendPhoto'));
});
