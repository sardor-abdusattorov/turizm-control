<?php

use App\Filament\Widgets\Dashboard\DashboardHeaderWidget;
use App\Models\Contract;
use App\Models\ContractApprover;
use App\Models\User;
use App\Services\Telegram\BotConversationState;
use App\Services\Telegram\TelegramBot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

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

it('renders contract title and amount inside the awaiting list body', function () {
    $approver = User::factory()->withTelegram('670')->create(['status' => User::STATUS_ACTIVE]);
    $contract = Contract::factory()->create([
        'status' => Contract::STATUS_IN_REVIEW,
        'title' => 'Договор на поставку оборудования',
    ]);
    ContractApprover::factory()->create([
        'contract_id' => $contract->id,
        'user_id' => $approver->id,
        'order' => 1,
        'status' => ContractApprover::STATUS_PENDING,
    ]);

    app(TelegramBot::class)->handleUpdate([
        'callback_query' => [
            'id' => 'cb',
            'from' => ['id' => 670],
            'message' => ['message_id' => 1],
            'data' => 'aw:1',
        ],
    ]);

    Http::assertSent(function ($request) use ($contract) {
        if (! str_contains($request->url(), '/editMessageText')) {
            return false;
        }

        $text = $request['text'] ?? '';
        $kb = collect($request['reply_markup']['inline_keyboard'] ?? [])->flatten(1);

        return str_contains($text, 'Договор на поставку оборудования')
            && str_contains($text, "№ {$contract->number}")
            && $kb->contains(fn ($btn) => ($btn['callback_data'] ?? null) === "view:{$contract->id}");
    });
});

it('confirms then deletes the telegram link on /unlink', function () {
    $user = User::factory()->withTelegram('888')->create();

    // /unlink — bot replies with the confirmation card
    app(TelegramBot::class)->handleUpdate([
        'message' => [
            'chat' => ['id' => 888],
            'text' => '/unlink',
        ],
    ]);

    // user taps the confirm button
    app(TelegramBot::class)->handleUpdate([
        'callback_query' => [
            'id' => 'cb',
            'from' => ['id' => 888],
            'message' => ['message_id' => 1],
            'data' => 'unlink',
        ],
    ]);

    expect($user->fresh()->telegram)->toBeNull();
});

it('offers the Telegram connect prompt for users who have not linked', function () {
    $user = User::factory()->create();

    actingAs($user);

    expect((new DashboardHeaderWidget)->shouldOfferTelegram())->toBeTrue();

    Livewire::test(DashboardHeaderWidget::class)
        ->assertSee(__('app.label.telegram_nag_title'))
        ->assertSee(route('telegram.connect'))
        ->assertSee(__('app.action.close'));
});

it('hides the Telegram connect prompt once the user has linked', function () {
    $user = User::factory()->withTelegram('999')->create();

    actingAs($user);

    expect((new DashboardHeaderWidget)->shouldOfferTelegram())->toBeFalse();

    Livewire::test(DashboardHeaderWidget::class)
        ->assertDontSee(__('app.label.telegram_nag_title'));
});

it('shows the history menu item when the user has past approvals', function () {
    $approver = User::factory()->withTelegram('700')->create(['status' => User::STATUS_ACTIVE]);

    // One approved + one rejected — both go into history.
    foreach ([
        [ContractApprover::STATUS_APPROVED, Contract::STATUS_APPROVED],
        [ContractApprover::STATUS_REJECTED, Contract::STATUS_REJECTED],
    ] as [$approverStatus, $contractStatus]) {
        $contract = Contract::factory()->create(['status' => $contractStatus]);
        ContractApprover::factory()->create([
            'contract_id' => $contract->id,
            'user_id' => $approver->id,
            'order' => 1,
            'status' => $approverStatus,
            'acted_at' => now(),
        ]);
    }

    app(TelegramBot::class)->handleUpdate([
        'message' => ['chat' => ['id' => 700], 'text' => '/menu'],
    ]);

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        $kb = collect($request['reply_markup']['inline_keyboard'] ?? [])->flatten(1);

        return $kb->contains(fn ($btn) => ($btn['callback_data'] ?? null) === 'hist:1');
    });
});

it('lists the contracts the user has acted on under /hist', function () {
    $approver = User::factory()->withTelegram('701')->create(['status' => User::STATUS_ACTIVE]);
    $contract = Contract::factory()->create(['status' => Contract::STATUS_APPROVED]);
    ContractApprover::factory()->create([
        'contract_id' => $contract->id,
        'user_id' => $approver->id,
        'order' => 1,
        'status' => ContractApprover::STATUS_APPROVED,
        'acted_at' => now(),
    ]);

    app(TelegramBot::class)->handleUpdate([
        'callback_query' => [
            'id' => 'cb',
            'from' => ['id' => 701],
            'message' => ['message_id' => 1],
            'data' => 'hist:1',
        ],
    ]);

    Http::assertSent(function ($request) use ($contract) {
        if (! str_contains($request->url(), '/editMessageText')) {
            return false;
        }

        $kb = collect($request['reply_markup']['inline_keyboard'] ?? [])->flatten(1);

        return $kb->contains(fn ($btn) => ($btn['callback_data'] ?? null) === "view:{$contract->id}");
    });
});

it('shows the all-contracts menu item only to super admins', function () {
    Role::findOrCreate('super_admin', 'web');

    $admin = User::factory()->withTelegram('801')->create();
    $admin->assignRole('super_admin');

    Contract::factory()->count(2)->create();

    app(TelegramBot::class)->handleUpdate([
        'message' => ['chat' => ['id' => 801], 'text' => '/menu'],
    ]);

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        $kb = collect($request['reply_markup']['inline_keyboard'] ?? [])->flatten(1);

        return $kb->contains(fn ($btn) => ($btn['callback_data'] ?? null) === 'all:1');
    });
});

it('refuses the /all callback for users without view-all rights', function () {
    User::factory()->withTelegram('802')->create();

    app(TelegramBot::class)->handleUpdate([
        'callback_query' => [
            'id' => 'cb',
            'from' => ['id' => 802],
            'message' => ['message_id' => 1],
            'data' => 'all:1',
        ],
    ]);

    Http::assertSent(fn ($request) => str_contains($request->url(), '/answerCallbackQuery')
        && str_contains($request['text'] ?? '', __('app.telegram.not_allowed')));
});

afterEach(function () {
    Cache::flush();
});
