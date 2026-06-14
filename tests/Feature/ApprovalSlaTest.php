<?php

use App\Models\Contract;
use App\Models\ContractApprover;
use App\Models\Settings;
use App\Models\User;
use App\Services\Contracts\ContractWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['settings.cache' => false]);
    Settings::set('approval.sla_days', 2);
    clear_settings_cache();
});

function slaChain(int $count = 2): array
{
    $responsible = User::factory()->create();
    $approvers = User::factory()->count($count)->create();

    $contract = Contract::factory()->create([
        'responsible_id' => $responsible->id,
        'status' => Contract::STATUS_DRAFT,
    ]);

    foreach ($approvers as $index => $approver) {
        ContractApprover::factory()->create([
            'contract_id' => $contract->id,
            'user_id' => $approver->id,
            'order' => $index + 1,
        ]);
    }

    return [$contract->refresh(), $responsible, $approvers];
}

it('sets a due date on the first approver when the contract is submitted', function () {
    [$contract, $responsible, $approvers] = slaChain();
    actingAs($responsible);

    app(ContractWorkflow::class)->submit($contract);

    $first = $contract->approvers()->where('order', 1)->first();

    expect($first->due_at)->not->toBeNull()
        ->and($first->due_at->isAfter(now()->addDay()))->toBeTrue()
        ->and($first->due_at->isBefore(now()->addDays(3)))->toBeTrue();
});

it('starts the clock for the next approver after the current one approves', function () {
    [$contract, , $approvers] = slaChain(2);
    $contract->update(['status' => Contract::STATUS_IN_REVIEW]);
    actingAs($approvers->first());

    app(ContractWorkflow::class)->approve($contract, $approvers->first());

    $second = $contract->approvers()->where('order', 2)->first();

    expect($second->due_at)->not->toBeNull();
});

it('flags an approver as overdue past the deadline', function () {
    $approver = ContractApprover::factory()->create([
        'status' => ContractApprover::STATUS_PENDING,
        'due_at' => now()->subHour(),
    ]);

    expect($approver->isOverdue())->toBeTrue()
        ->and($approver->needsReminder())->toBeTrue();
});

it('does not remind an approver whose deadline is still far away', function () {
    $approver = ContractApprover::factory()->create([
        'status' => ContractApprover::STATUS_PENDING,
        'due_at' => now()->addDays(2),
    ]);

    expect($approver->isOverdue())->toBeFalse()
        ->and($approver->needsReminder())->toBeFalse();
});

it('sends a reminder for the overdue current approver via the command', function () {
    [$contract, , $approvers] = slaChain(2);
    $contract->update(['status' => Contract::STATUS_IN_REVIEW]);

    $current = $contract->approvers()->where('order', 1)->first();
    $current->update(['due_at' => now()->subHours(2), 'reminder_sent_at' => null]);

    artisan('contracts:send-approval-reminders')->assertSuccessful();

    expect($current->fresh()->reminder_sent_at)->not->toBeNull();

    $this->assertDatabaseHas('notifications', [
        'notifiable_id' => $approvers->first()->id,
        'notifiable_type' => User::class,
    ]);
});

it('pushes a telegram message when a bot token and chat id are present', function () {
    config(['services.telegram.bot_token' => 'test-token']);
    Http::fake(['*/sendMessage' => Http::response(['ok' => true])]);

    [$contract, $responsible, $approvers] = slaChain(1);
    $approvers->first()->update(['telegram_chat_id' => '123456']);
    actingAs($responsible);

    app(ContractWorkflow::class)->submit($contract);

    Http::assertSent(fn ($request) => str_contains($request->url(), '/bottest-token/sendMessage')
        && $request['chat_id'] === '123456');
});

it('logs a workflow activity entry on submit (sanity)', function () {
    [$contract, $responsible] = slaChain(1);
    actingAs($responsible);

    app(ContractWorkflow::class)->submit($contract);

    expect(Activity::where('event', 'Contract Submitted')->exists())->toBeTrue();
});
