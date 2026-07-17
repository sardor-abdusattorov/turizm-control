<?php

use App\Jobs\SendTelegramMessage;
use App\Models\Contract;
use App\Models\ContractApprover;
use App\Models\User;
use App\Services\Contracts\ContractWorkflow;
use App\Services\Telegram\TelegramBot;
use App\Services\Telegram\TelegramService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\artisan;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.telegram.bot_token' => 'test-token',
        'services.telegram.webhook_secret' => 'test-secret',
    ]);
});

it('marks the chat blocked when Telegram answers 403', function () {
    $user = User::factory()->withTelegram('700')->create();
    Http::fake(['api.telegram.org/*' => Http::response('bot was blocked by the user', 403)]);

    app(TelegramService::class)->send('700', 'hello');

    expect($user->telegram->fresh()->blocked_at)->not->toBeNull();
});

it('skips delivery to a blocked chat without burning retries', function () {
    $user = User::factory()->withTelegram('701')->create();
    $user->telegram->update(['blocked_at' => now()]);
    Http::fake();

    (new SendTelegramMessage('701', 'hello'))->handle(app(TelegramService::class));

    Http::assertNothingSent();
});

it('lifts the blocked mark when the user talks to the bot again', function () {
    $user = User::factory()->withTelegram('702')->create();
    $user->telegram->update(['blocked_at' => now()]);
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

    app(TelegramBot::class)->handleUpdate([
        'message' => ['chat' => ['id' => 702], 'text' => '/menu'],
    ]);

    expect($user->telegram->fresh()->blocked_at)->toBeNull();
});

it('publishes the command menu when installing the webhook', function () {
    config(['app.url' => 'https://example.test']);
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

    artisan('telegram:webhook:install', ['--url' => 'https://example.test/hook'])
        ->assertSuccessful();

    Http::assertSentCount(4); // setWebhook + setMyCommands ×3 (default, ru, uz)
    Http::assertSent(fn ($request) => str_contains($request->url(), 'setMyCommands'));
});

it('adds an in-bot card button to workflow notifications', function () {
    Bus::fake();
    $responsible = User::factory()->withTelegram('703')->create();
    $approver = asApprover(User::factory()->create());
    $contract = Contract::factory()->create([
        'responsible_id' => $responsible->id,
        'status' => Contract::STATUS_IN_REVIEW,
    ]);
    ContractApprover::factory()->create([
        'contract_id' => $contract->id,
        'user_id' => $approver->id,
        'order' => 1,
    ]);

    app(ContractWorkflow::class)->reject($contract, $approver, 'нет');

    Bus::assertDispatched(SendTelegramMessage::class, function (SendTelegramMessage $job) use ($contract) {
        $flat = json_encode($job->inlineKeyboard);

        return $job->chatId === '703' && str_contains($flat, "view:{$contract->id}");
    });
});
