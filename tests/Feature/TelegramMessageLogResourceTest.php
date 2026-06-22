<?php

use App\Filament\Resources\TelegramMessageLogs\Pages\ListTelegramMessageLogs;
use App\Models\TelegramMessageLog;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Actions\ViewAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['services.telegram.bot_token' => 'test-token']);

    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate('super_admin', 'web'));
    actingAs($admin->fresh());
});

it('lists telegram message logs for a super admin', function () {
    $logs = collect([
        TelegramMessageLog::create([
            'chat_id' => '111',
            'method' => 'sendMessage',
            'text' => 'Hello <b>team</b>',
            'ok' => true,
            'status' => 200,
        ]),
        TelegramMessageLog::create([
            'chat_id' => '222',
            'method' => 'sendMessage',
            'text' => 'Broken',
            'ok' => false,
            'status' => 400,
            'error' => '{"ok":false,"error_code":400,"description":"Bad Request: chat not found"}',
        ]),
    ]);

    Livewire::test(ListTelegramMessageLogs::class)
        ->assertCanSeeTableRecords($logs);
});

it('opens the detail modal without errors', function () {
    $log = TelegramMessageLog::create([
        'chat_id' => '333',
        'method' => 'editMessageText',
        'text' => 'Updated <i>card</i>',
        'ok' => false,
        'status' => 400,
        'error' => '{"ok":false,"error_code":400,"description":"message is not modified"}',
    ]);

    Livewire::test(ListTelegramMessageLogs::class)
        ->mountAction(TestAction::make(ViewAction::getDefaultName())->table($log))
        ->assertHasNoErrors();
});
