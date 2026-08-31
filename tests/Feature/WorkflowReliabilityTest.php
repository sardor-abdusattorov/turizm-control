<?php

use App\Models\Contract;
use App\Models\ContractApprover;
use App\Models\User;
use App\Services\Contracts\ContractWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    Http::fake(['*' => Http::response(['ok' => true])]);
});

function reliabilityChain(int $count = 2): array
{
    $responsible = User::factory()->create();
    $approvers = asApprover(User::factory()->count($count)->create());

    $contract = Contract::factory()->withDossier()->create([
        'responsible_id' => $responsible->id,
        'status' => Contract::STATUS_DRAFT,
    ]);

    foreach ($approvers as $index => $approver) {
        ContractApprover::factory()->create([
            'contract_id' => $contract->id,
            'user_id' => $approver->id,
            'order' => $index + 1,
            'status' => ContractApprover::STATUS_QUEUED,
        ]);
    }

    return [$contract->refresh(), $responsible, $approvers];
}

it('refuses a second submit and never doubles the chain', function () {
    [$contract, $responsible] = reliabilityChain();
    actingAs($responsible);
    $workflow = app(ContractWorkflow::class);
    $before = $contract->approvers()->count();

    expect($workflow->submit($contract))->toBeTrue()
        ->and($workflow->submit($contract))->toBeFalse()
        ->and($contract->fresh()->approvers()->count())->toBe($before)
        ->and($contract->fresh()->status)->toBe(Contract::STATUS_IN_REVIEW);
});

it('refuses a duplicate approve from the same user', function () {
    [$contract, , $approvers] = reliabilityChain(2);
    actingAs($approvers[0]);
    $workflow = app(ContractWorkflow::class);
    $workflow->submit($contract, $contract->responsible);

    expect($workflow->approve($contract, $approvers[0]))->toBeTrue()
        ->and($workflow->approve($contract, $approvers[0]))->toBeFalse();

    $pending = $contract->fresh()->approvers()
        ->where('status', ContractApprover::STATUS_PENDING)
        ->get();

    expect($pending)->toHaveCount(1)
        ->and($pending->first()->user_id)->toBe($approvers[1]->id);
});

it('reports failure when the director is already in the active chain', function () {
    Role::findOrCreate('director', 'web');
    $director = asApprover(User::factory()->create());
    $director->assignRole('director');
    $responsible = User::factory()->create();

    $contract = Contract::factory()->withDossier()->create([
        'responsible_id' => $responsible->id,
        'status' => Contract::STATUS_PENDING_DIRECTOR,
    ]);
    ContractApprover::factory()->approved()->create([
        'contract_id' => $contract->id,
        'user_id' => $director->id,
        'order' => 1,
    ]);

    expect(app(ContractWorkflow::class)->submitToDirector($contract, $responsible))->toBeFalse()
        ->and($contract->fresh()->status)->toBe(Contract::STATUS_PENDING_DIRECTOR);
});

it('returns a rejected contract to work with the same chain re-queued', function () {
    [$contract, $responsible, $approvers] = reliabilityChain(2);
    $workflow = app(ContractWorkflow::class);
    $workflow->submit($contract, $responsible);
    $workflow->reject($contract, $approvers[0], 'Сумма не совпадает');

    expect($contract->fresh()->status)->toBe(Contract::STATUS_REJECTED);

    actingAs($responsible);
    expect($workflow->returnToWork($contract, $responsible))->toBeTrue();

    $fresh = $contract->fresh();
    $queued = $fresh->activeApprovers()->pluck('user_id')->all();

    expect($fresh->status)->toBe(Contract::STATUS_DRAFT)
        ->and($queued)->toBe([$approvers[0]->id, $approvers[1]->id])
        ->and($fresh->canBeSubmittedBy($responsible))->toBeTrue();
});

it('does not return a contract to work for an outsider', function () {
    [$contract, $responsible, $approvers] = reliabilityChain();
    $workflow = app(ContractWorkflow::class);
    $workflow->submit($contract, $responsible);
    $workflow->reject($contract, $approvers[0], 'нет');

    $outsider = User::factory()->create();

    expect($workflow->returnToWork($contract, $outsider))->toBeFalse()
        ->and($contract->fresh()->status)->toBe(Contract::STATUS_REJECTED);
});

it('reassigns the stuck current approver to another user with the SLA restarted', function () {
    [$contract, $responsible, $approvers] = reliabilityChain(2);
    $workflow = app(ContractWorkflow::class);
    $workflow->submit($contract, $responsible);

    $replacement = asApprover(User::factory()->create());
    $admin = User::factory()->create();

    expect($workflow->reassignCurrentApprover($contract, $replacement, $admin))->toBeTrue();

    $fresh = $contract->fresh();
    $current = $fresh->currentApprover();
    $skipped = $fresh->approvers()->where('status', ContractApprover::STATUS_SKIPPED)->first();

    expect($current->user_id)->toBe($replacement->id)
        ->and($current->due_at)->not->toBeNull()
        ->and($skipped->user_id)->toBe($approvers[0]->id)
        ->and($current->order)->toBe($skipped->order);

    expect($workflow->approve($contract->fresh(), $replacement))->toBeTrue();
});

it('does not remind a deactivated approver', function () {
    [$contract, $responsible, $approvers] = reliabilityChain(1);
    $workflow = app(ContractWorkflow::class);
    $workflow->submit($contract, $responsible);

    $step = $contract->fresh()->currentApprover();
    $step->update(['due_at' => now()->subHours(3), 'reminder_sent_at' => null]);
    $approvers[0]->update(['status' => false]);

    artisan('contracts:send-approval-reminders')->assertSuccessful();

    expect($step->fresh()->reminder_sent_at)->toBeNull();
});
