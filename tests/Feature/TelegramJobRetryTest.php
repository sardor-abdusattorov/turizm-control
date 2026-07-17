<?php

use App\Jobs\SendTelegramMessage;
use App\Services\Telegram\TelegramService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.telegram.bot_token' => 'test-token']);
});

it('throws when the Telegram API rejects the message so the job retries', function () {
    Http::fake(['api.telegram.org/*' => Http::response('server error', 500)]);

    $job = new SendTelegramMessage('12345', 'hello');

    expect(fn () => $job->handle(app(TelegramService::class)))
        ->toThrow(RuntimeException::class);
});

it('completes quietly when the message is delivered', function () {
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true], 200)]);

    $job = new SendTelegramMessage('12345', 'hello');
    $job->handle(app(TelegramService::class));

    Http::assertSentCount(1);
});

it('skips silently when there is no chat id', function () {
    Http::fake();

    (new SendTelegramMessage(null, 'hello'))->handle(app(TelegramService::class));

    Http::assertNothingSent();
});
