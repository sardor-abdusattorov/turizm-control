<?php

use App\Models\Contract;
use App\Models\ContractApprover;
use App\Models\User;
use App\Services\Telegram\BotConversationState;
use App\Services\Telegram\TelegramBot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.telegram.bot_token' => 'test-token',
        'services.telegram.bot_username' => 'turizm_bot',
        'services.telegram.webhook_secret' => 'hook-secret',
    ]);

    Http::fake(['*' => Http::response(['ok' => true])]);
});

it('shows the locale picker after linking', function () {
    $user = User::factory()->create();
    $url = app(TelegramBot::class)->connectUrl($user);
    $token = str($url)->after('start=')->value();

    app(TelegramBot::class)->handleUpdate([
        'message' => [
            'chat' => ['id' => 111],
            'text' => "/start {$token}",
        ],
    ]);

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        $body = $request->data();

        return is_array($body['reply_markup'] ?? null)
            && collect($body['reply_markup']['inline_keyboard'])
                ->flatten(1)
                ->contains(fn ($btn) => ($btn['callback_data'] ?? null) === 'lang:ru');
    });
});

it('persists the chosen locale on the telegram_users row', function () {
    $user = User::factory()->withTelegram('222')->create();

    app(TelegramBot::class)->handleUpdate([
        'callback_query' => [
            'id' => 'cb',
            'from' => ['id' => 222],
            'data' => 'lang:uz',
        ],
    ]);

    expect($user->fresh()->telegram->locale)->toBe('uz');
});

it('starts the reject conversation and stores state for the next text message', function () {
    $approver = User::factory()->withTelegram('333')->create();
    $contract = Contract::factory()->create(['status' => Contract::STATUS_IN_REVIEW]);
    ContractApprover::factory()->create([
        'contract_id' => $contract->id, 'user_id' => $approver->id, 'order' => 1,
        'status' => ContractApprover::STATUS_PENDING,
    ]);

    app(TelegramBot::class)->handleUpdate([
        'callback_query' => [
            'id' => 'cb',
            'from' => ['id' => 333],
            'message' => ['message_id' => 99],
            'data' => "reject:{$contract->id}",
        ],
    ]);

    $state = app(BotConversationState::class)->get('333');

    expect($state)->toMatchArray([
        'action' => 'reject',
        'contract_id' => $contract->id,
    ]);
});

it('rejects the contract with the text reply and clears the conversation state', function () {
    $approver = User::factory()->withTelegram('444')->create();
    $contract = Contract::factory()->create(['status' => Contract::STATUS_IN_REVIEW]);
    ContractApprover::factory()->create([
        'contract_id' => $contract->id, 'user_id' => $approver->id, 'order' => 1,
        'status' => ContractApprover::STATUS_PENDING,
    ]);

    $bot = app(TelegramBot::class);
    $bot->handleUpdate([
        'callback_query' => [
            'id' => 'cb1',
            'from' => ['id' => 444],
            'message' => ['message_id' => 1],
            'data' => "reject:{$contract->id}",
        ],
    ]);
    $bot->handleUpdate([
        'message' => [
            'chat' => ['id' => 444],
            'text' => 'Сумма не сходится с приложением №2.',
        ],
    ]);

    $contract->refresh();

    expect($contract->status)->toBe(Contract::STATUS_REJECTED)
        ->and(app(BotConversationState::class)->get('444'))->toBeNull()
        ->and($contract->approvers()->where('user_id', $approver->id)->first()->comment)
        ->toBe('Сумма не сходится с приложением №2.');
});

it('warns when an unlinked chat sends a callback', function () {
    app(TelegramBot::class)->handleUpdate([
        'callback_query' => [
            'id' => 'cb',
            'from' => ['id' => 999],
            'data' => 'menu',
        ],
    ]);

    Http::assertSent(fn ($request) => str_contains($request->url(), '/answerCallbackQuery')
        && str_contains($request['text'] ?? '', __('app.telegram.not_linked')));
});

it('clears the conversation state on cancel', function () {
    $user = User::factory()->withTelegram('555')->create();
    app(BotConversationState::class)->set('555', 'reject', ['contract_id' => 7]);

    app(TelegramBot::class)->handleUpdate([
        'callback_query' => [
            'id' => 'cb',
            'from' => ['id' => 555],
            'data' => 'cancel',
        ],
    ]);

    expect(app(BotConversationState::class)->get('555'))->toBeNull();
});

it('renders the awaiting list paginated', function () {
    $approver = User::factory()->withTelegram('666')->create();

    for ($i = 0; $i < 7; $i++) {
        $contract = Contract::factory()->create(['status' => Contract::STATUS_IN_REVIEW]);
        ContractApprover::factory()->create([
            'contract_id' => $contract->id,
            'user_id' => $approver->id,
            'order' => 1,
            'status' => ContractApprover::STATUS_PENDING,
        ]);
    }

    app(TelegramBot::class)->handleUpdate([
        'callback_query' => [
            'id' => 'cb',
            'from' => ['id' => 666],
            'message' => ['message_id' => 1],
            'data' => 'aw:1',
        ],
    ]);

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/editMessageText')) {
            return false;
        }

        $kb = collect($request['reply_markup']['inline_keyboard'] ?? [])->flatten(1);

        return $kb->contains(fn ($btn) => ($btn['callback_data'] ?? null) === 'aw:2');
    });
});

afterEach(function () {
    Cache::flush();
});
