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

it('asks for confirmation and links the chat only after «yes, it is me»', function () {
    $user = User::factory()->create();
    $url = app(TelegramBot::class)->connectUrl($user);
    $token = str($url)->after('start=')->value();

    app(TelegramBot::class)->handleUpdate([
        'message' => [
            'chat' => ['id' => 987654],
            'from' => ['id' => 987654, 'username' => 'sardor_uz'],
            'text' => "/start {$token}",
        ],
    ]);

    expect($user->fresh()->telegram)->toBeNull();

    app(TelegramBot::class)->handleUpdate([
        'callback_query' => [
            'id' => 'cb-link',
            'from' => ['id' => 987654, 'username' => 'sardor_uz'],
            'message' => ['message_id' => 5],
            'data' => "lnk:{$token}",
        ],
    ]);

    $telegram = $user->fresh()->telegram;

    expect($telegram?->chat_id)->toBe('987654')
        ->and($telegram?->username)->toBe('sardor_uz')
        ->and($telegram?->last_seen_at)->not->toBeNull();
});

it('declining the link confirmation leaves the account untouched', function () {
    $user = User::factory()->create();
    $url = app(TelegramBot::class)->connectUrl($user);
    $token = str($url)->after('start=')->value();

    app(TelegramBot::class)->handleUpdate([
        'message' => [
            'chat' => ['id' => 111222],
            'from' => ['id' => 111222],
            'text' => "/start {$token}",
        ],
    ]);

    app(TelegramBot::class)->handleUpdate([
        'callback_query' => [
            'id' => 'cb-cancel',
            'from' => ['id' => 111222],
            'message' => ['message_id' => 6],
            'data' => 'lnkc',
        ],
    ]);

    expect($user->fresh()->telegram)->toBeNull();
});

it('a consumed confirmation token cannot be replayed', function () {
    $user = User::factory()->create();
    $url = app(TelegramBot::class)->connectUrl($user);
    $token = str($url)->after('start=')->value();

    $confirm = fn (string $chatId) => app(TelegramBot::class)->handleUpdate([
        'callback_query' => [
            'id' => 'cb-'.$chatId,
            'from' => ['id' => (int) $chatId],
            'message' => ['message_id' => 7],
            'data' => "lnk:{$token}",
        ],
    ]);

    $confirm('333');
    expect($user->fresh()->telegram?->chat_id)->toBe('333');

    $confirm('444');
    expect($user->fresh()->telegram?->chat_id)->toBe('333');
});

it('re-linking a chat that belonged to another user releases the old row', function () {
    $old = User::factory()->withTelegram('550001')->create();
    $new = User::factory()->create();

    $url = app(TelegramBot::class)->connectUrl($new);
    $token = str($url)->after('start=')->value();

    app(TelegramBot::class)->handleUpdate([
        'callback_query' => [
            'id' => 'cb-relink',
            'from' => ['id' => 550001],
            'message' => ['message_id' => 8],
            'data' => "lnk:{$token}",
        ],
    ]);

    expect($new->fresh()->telegram?->chat_id)->toBe('550001')
        ->and($old->fresh()->telegram)->toBeNull();
});

it('rejects the webhook without the secret header and accepts a fully signed call', function () {
    post('/telegram/webhook/wrong-secret', [])->assertForbidden();

    post('/telegram/webhook/hook-secret', [
        'message' => ['chat' => ['id' => 1], 'text' => '/start'],
    ])->assertForbidden();

    post('/telegram/webhook/hook-secret', [
        'message' => ['chat' => ['id' => 1], 'text' => '/start'],
    ], ['X-Telegram-Bot-Api-Secret-Token' => 'hook-secret'])->assertOk();
});

it('opens a comment step on approve and stores the typed comment', function () {
    $approver = asApprover(User::factory()->withTelegram('555')->create());
    $contract = Contract::factory()->create(['status' => Contract::STATUS_IN_REVIEW]);
    ContractApprover::factory()->create([
        'contract_id' => $contract->id,
        'user_id' => $approver->id,
        'order' => 1,
    ]);

    app(TelegramBot::class)->handleUpdate([
        'callback_query' => [
            'id' => 'cb1',
            'from' => ['id' => 555],
            'data' => "approve:{$contract->id}",
        ],
    ]);

    expect($contract->fresh()->status)->toBe(Contract::STATUS_IN_REVIEW);

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
    $approver = asApprover(User::factory()->withTelegram('556')->create());
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

it('keeps the reject flow open when a non-text message arrives', function () {
    $approver = asApprover(User::factory()->withTelegram('557')->create());
    $contract = Contract::factory()->create(['status' => Contract::STATUS_IN_REVIEW]);
    ContractApprover::factory()->create([
        'contract_id' => $contract->id,
        'user_id' => $approver->id,
        'order' => 1,
    ]);

    app(TelegramBot::class)->handleUpdate([
        'callback_query' => [
            'id' => 'cb1',
            'from' => ['id' => 557],
            'data' => "reject:{$contract->id}",
        ],
    ]);

    app(TelegramBot::class)->handleUpdate([
        'message' => [
            'chat' => ['id' => 557],
            'sticker' => ['file_id' => 'abc'],
        ],
    ]);

    expect($contract->fresh()->status)->toBe(Contract::STATUS_IN_REVIEW);

    app(TelegramBot::class)->handleUpdate([
        'message' => [
            'chat' => ['id' => 557],
            'text' => 'Сумма не совпадает с приказом',
        ],
    ]);

    expect($contract->fresh()->status)->toBe(Contract::STATUS_REJECTED)
        ->and($contract->approvers()->where('user_id', $approver->id)->first()->comment)
        ->toBe('Сумма не совпадает с приказом');
});

it('ignores a redelivered update with the same update_id', function () {
    $approver = asApprover(User::factory()->withTelegram('558')->create());
    $contract = Contract::factory()->create(['status' => Contract::STATUS_IN_REVIEW]);
    ContractApprover::factory()->create([
        'contract_id' => $contract->id,
        'user_id' => $approver->id,
        'order' => 1,
    ]);

    $update = [
        'update_id' => 424242,
        'callback_query' => [
            'id' => 'cb-dup',
            'from' => ['id' => 558],
            'data' => "apnc:{$contract->id}",
        ],
    ];

    app(TelegramBot::class)->handleUpdate($update);
    expect($contract->fresh()->status)->toBe(Contract::STATUS_APPROVED);

    Http::fake(['*' => Http::response(['ok' => true])]);
    app(TelegramBot::class)->handleUpdate($update);

    Http::assertNothingSent();
});
