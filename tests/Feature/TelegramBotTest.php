<?php

use App\Models\Contract;
use App\Models\ContractApprover;
use App\Models\User;
use App\Services\Telegram\TelegramBot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\post;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.telegram.bot_token' => 'test-token',
        'services.telegram.bot_username' => 'turizm_bot',
        'services.telegram.webhook_secret' => 'hook-secret',
    ]);

    Http::fake(['*' => Http::response(['ok' => true])]);
});

it('generates a connect deep link that maps a token to the user', function () {
    $user = User::factory()->create();

    $url = app(TelegramBot::class)->connectUrl($user);

    expect($url)->toStartWith('https://t.me/turizm_bot?start=');

    $token = str($url)->after('start=')->value();
    expect(Cache::get("telegram_link:{$token}"))->toBe($user->id);
});

it('links the chat id to the user when /start arrives with a valid token', function () {
    $user = User::factory()->create();
    $url = app(TelegramBot::class)->connectUrl($user);
    $token = str($url)->after('start=')->value();

    app(TelegramBot::class)->handleUpdate([
        'message' => [
            'chat' => ['id' => 987654],
            'text' => "/start {$token}",
        ],
    ]);

    expect($user->fresh()->telegram?->chat_id)->toBe('987654');
});

it('rejects the webhook with a wrong secret and accepts the right one', function () {
    post('/telegram/webhook/wrong-secret', [])->assertForbidden();

    post('/telegram/webhook/hook-secret', [
        'message' => ['chat' => ['id' => 1], 'text' => '/start'],
    ])->assertOk();
});

it('opens a comment step on approve and stores the typed comment', function () {
    $approver = User::factory()->withTelegram('555')->create();
    $contract = Contract::factory()->create(['status' => Contract::STATUS_IN_REVIEW]);
    ContractApprover::factory()->create([
        'contract_id' => $contract->id,
        'user_id' => $approver->id,
        'order' => 1,
    ]);

    // Tapping approve must NOT decide yet — it opens the optional comment step.
    app(TelegramBot::class)->handleUpdate([
        'callback_query' => [
            'id' => 'cb1',
            'from' => ['id' => 555],
            'data' => "approve:{$contract->id}",
        ],
    ]);

    expect($contract->fresh()->status)->toBe(Contract::STATUS_IN_REVIEW);

    // The next message is taken as the approval comment and finalises it.
    app(TelegramBot::class)->handleUpdate([
        'message' => [
            'chat' => ['id' => 555],
            'text' => 'Looks good to me',
        ],
    ]);

    expect($contract->fresh()->status)->toBe(Contract::STATUS_APPROVED)
        ->and($contract->approvers()->where('user_id', $approver->id)->first()->comment)
        ->toBe('Looks good to me');

    Http::assertSent(fn ($request) => str_contains($request->url(), '/answerCallbackQuery'));
});

it('approves without a comment via the shortcut button', function () {
    $approver = User::factory()->withTelegram('556')->create();
    $contract = Contract::factory()->create(['status' => Contract::STATUS_IN_REVIEW]);
    ContractApprover::factory()->create([
        'contract_id' => $contract->id,
        'user_id' => $approver->id,
        'order' => 1,
    ]);

    app(TelegramBot::class)->handleUpdate([
        'callback_query' => [
            'id' => 'cb1',
            'from' => ['id' => 556],
            'data' => "apnc:{$contract->id}",
        ],
    ]);

    expect($contract->fresh()->status)->toBe(Contract::STATUS_APPROVED)
        ->and($contract->approvers()->where('user_id', $approver->id)->first()->comment)
        ->toBeNull();
});

it('does not approve when the caller is not the current approver', function () {
    $outsider = User::factory()->withTelegram('777')->create();
    $contract = Contract::factory()->create(['status' => Contract::STATUS_IN_REVIEW]);
    ContractApprover::factory()->create([
        'contract_id' => $contract->id,
        'user_id' => User::factory()->create()->id,
        'order' => 1,
    ]);

    app(TelegramBot::class)->handleUpdate([
        'callback_query' => [
            'id' => 'cb2',
            'from' => ['id' => 777],
            'data' => "approve:{$contract->id}",
        ],
    ]);

    expect($contract->fresh()->status)->toBe(Contract::STATUS_IN_REVIEW);
});
