<?php

use App\Enums\PaymentStatus;
use App\Jobs\SendTelegramMessage;
use App\Models\Contract;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\artisan;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.telegram.bot_token' => 'test-token',
        'services.telegram.bot_username' => 'turizm_bot',
    ]);

    Http::fake(['*' => Http::response(['ok' => true])]);
});

function approvedUnderpaidContract(User $manager, int $daysAgo, float $paidPercent = 40): Contract
{
    $contract = Contract::factory()->create(['responsible_id' => $manager->id]);

    $contract->forceFill([
        'status' => Contract::STATUS_APPROVED,
        'signed_at' => today()->subDays($daysAgo)->toDateString(),
        'paid_percent' => $paidPercent,
        'payment_status' => PaymentStatus::fromPercent($paidPercent)->value,
    ])->saveQuietly();

    return $contract;
}

it('reminds the responsible manager a week after approval while unpaid', function () {
    Queue::fake();

    $manager = User::factory()->withTelegram('6001')->create();
    $contract = approvedUnderpaidContract($manager, daysAgo: 14);

    artisan('contracts:send-payment-reminders')->assertSuccessful();

    Queue::assertPushed(SendTelegramMessage::class, fn (SendTelegramMessage $job) => $job->chatId === '6001'
        && str_contains($job->message, (string) $contract->number)
        && str_contains($job->message, '14'));
});

it('respects the weekly cadence — no reminder between the marks', function () {
    Queue::fake();

    $manager = User::factory()->withTelegram('6002')->create();
    approvedUnderpaidContract($manager, daysAgo: 10);

    artisan('contracts:send-payment-reminders')->assertSuccessful();

    Queue::assertNotPushed(SendTelegramMessage::class);
});

it('leaves fully paid contracts alone', function () {
    Queue::fake();

    $manager = User::factory()->withTelegram('6003')->create();
    approvedUnderpaidContract($manager, daysAgo: 14, paidPercent: 100);

    artisan('contracts:send-payment-reminders')->assertSuccessful();

    Queue::assertNotPushed(SendTelegramMessage::class);
});

it('ignores contracts approved less than a week ago', function () {
    Queue::fake();

    $manager = User::factory()->withTelegram('6004')->create();
    approvedUnderpaidContract($manager, daysAgo: 3);

    artisan('contracts:send-payment-reminders')->assertSuccessful();

    Queue::assertNotPushed(SendTelegramMessage::class);
});
