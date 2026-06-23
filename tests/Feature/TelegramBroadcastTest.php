<?php

use App\Filament\Pages\TelegramBroadcast;
use App\Models\User;
use App\Services\Telegram\TelegramBroadcaster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.telegram.bot_token' => 'test-token',
        'services.telegram.bot_username' => 'pr_kontrol_bot',
    ]);
    Http::fake(['*' => Http::response(['ok' => true])]);
});

it('sends the message to every linked telegram chat and counts them', function () {
    User::factory()->withTelegram('111')->create();
    User::factory()->withTelegram('222')->create();
    User::factory()->create(); // not linked — must be skipped

    $count = app(TelegramBroadcaster::class)->send('Plan o‘zgardi <important>');

    expect($count)->toBe(2);

    Http::assertSentCount(2);
    Http::assertSent(fn ($request) => str_contains($request->url(), '/sendMessage')
        && in_array($request['chat_id'] ?? null, ['111', '222'], true));
});

it('escapes html-special characters so the message is not broken', function () {
    User::factory()->withTelegram('999')->create();

    app(TelegramBroadcaster::class)->send('5 < 10 & ok');

    Http::assertSent(fn ($request) => str_contains($request['text'] ?? '', '5 &lt; 10 &amp; ok'));
});

it('lets a user with the broadcast permission open the page', function () {
    actingAs(userWithPermission('view_telegram_broadcast'));

    Livewire::test(TelegramBroadcast::class)->assertSuccessful();
});

it('hides the broadcast page from a user without the permission', function () {
    actingAs(User::factory()->create());

    expect(TelegramBroadcast::canAccess())->toBeFalse();
});
