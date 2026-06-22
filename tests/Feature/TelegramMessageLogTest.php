<?php

use App\Models\TelegramMessageLog;
use App\Services\Telegram\TelegramService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['services.telegram.bot_token' => 'test-token']);
});

it('logs a successful outgoing message with the recipient chat and text', function () {
    Http::fake(['*' => Http::response(['ok' => true], 200)]);

    app(TelegramService::class)->send('555', 'Hello <b>team</b>');

    $log = TelegramMessageLog::query()->latest('id')->first();

    expect($log)->not->toBeNull()
        ->and($log->method)->toBe('sendMessage')
        ->and($log->chat_id)->toBe('555')
        ->and($log->text)->toBe('Hello <b>team</b>')
        ->and($log->ok)->toBeTrue()
        ->and($log->status)->toBe(200);
});

it('logs a failed send with the error and ok=false', function () {
    Http::fake(['*' => Http::response(['ok' => false, 'description' => 'chat not found'], 400)]);

    app(TelegramService::class)->send('999', 'nope');

    $log = TelegramMessageLog::query()->latest('id')->first();

    expect($log->ok)->toBeFalse()
        ->and($log->status)->toBe(400)
        ->and($log->error)->toContain('chat not found');
});

it('does not log the answerCallbackQuery ACKs', function () {
    Http::fake(['*' => Http::response(['ok' => true], 200)]);

    app(TelegramService::class)->answerCallbackQuery('cb-1', 'done');

    expect(TelegramMessageLog::query()->count())->toBe(0);
});

it('does not write a log row when there is no bot token', function () {
    config(['services.telegram.bot_token' => null]);

    app(TelegramService::class)->send('555', 'silent');

    expect(TelegramMessageLog::query()->count())->toBe(0);
});
