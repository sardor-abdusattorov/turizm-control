<?php

use App\Jobs\SendTelegramMessage;
use App\Models\Contract;
use App\Models\User;
use App\Services\Contracts\ContractNotifier;
use App\Services\Telegram\TelegramBroadcaster;
use App\Services\Telegram\TelegramService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.telegram.bot_token' => 'test-token',
        'services.telegram.bot_username' => 'turizm_bot',
    ]);

    Http::fake(['*' => Http::response(['ok' => true])]);
});

it('queues contract notifications instead of sending inline', function () {
    Queue::fake();

    $manager = User::factory()->withTelegram('9001')->create();
    $contract = Contract::factory()->create(['responsible_id' => $manager->id]);

    app(ContractNotifier::class)->notifyApproved($contract);

    Queue::assertPushed(SendTelegramMessage::class, fn (SendTelegramMessage $job) => $job->chatId === '9001'
        && str_contains($job->message, $contract->number));

    // Nothing hit the Telegram API from the request path itself.
    Http::assertNothingSent();
});

it('does not queue anything for a recipient without a linked chat', function () {
    Queue::fake();

    $manager = User::factory()->create();
    $contract = Contract::factory()->create(['responsible_id' => $manager->id]);

    app(ContractNotifier::class)->notifyApproved($contract);

    Queue::assertNotPushed(SendTelegramMessage::class);
});

it('the job delivers the message through the telegram api', function () {
    (new SendTelegramMessage('123', '<b>hello</b>'))->handle(app(TelegramService::class));

    Http::assertSent(fn ($request) => str_contains($request->url(), '/sendMessage')
        && ($request['chat_id'] ?? null) === '123'
        && ($request['text'] ?? null) === '<b>hello</b>');
});

it('broadcast queues one job per linked chat', function () {
    Queue::fake();

    User::factory()->withTelegram('111')->create();
    User::factory()->withTelegram('222')->create();
    User::factory()->create();

    $count = app(TelegramBroadcaster::class)->send('Plan changed');

    expect($count)->toBe(2);
    Queue::assertPushed(SendTelegramMessage::class, 2);
});
