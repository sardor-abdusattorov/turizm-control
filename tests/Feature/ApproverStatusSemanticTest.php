<?php

use App\Models\Contract;
use App\Models\ContractApprover;
use App\Models\Department;
use App\Models\User;
use App\Services\Contracts\ContractWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
});

function chainForSubmit(int $approverCount = 2): array
{
    $responsible = User::factory()->create();
    $approvers = asApprover(User::factory()->count($approverCount)->create());

    $contract = Contract::factory()->withDocument()->create([
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

it('builds the approval chain from the global flow with QUEUED rows, not PENDING', function () {
    $legal = Department::factory()->create(['code' => 'legal']);
    $accounting = Department::factory()->create(['code' => 'accounting']);
    $direction = Department::factory()->create(['code' => 'direction']);

    foreach ([$legal, $accounting, $direction] as $dept) {
        User::factory()->create([
            'status' => User::STATUS_ACTIVE,
            'department_id' => $dept->id,
        ]);
    }

    $contract = Contract::factory()->create();
    $contract->buildApprovalChainFromFlow();

    $approvers = $contract->fresh()->approvers;
    expect($approvers)->not->toBeEmpty();
    foreach ($approvers as $approver) {
        expect($approver->status)->toBe(ContractApprover::STATUS_QUEUED);
    }
});

it('promotes the first QUEUED approver to PENDING on submit and starts the SLA clock', function () {
    [$contract, $responsible, $approvers] = chainForSubmit(2);

    actingAs($responsible);
    app(ContractWorkflow::class)->submit($contract);

    $first = $contract->fresh()->approvers->firstWhere('order', 1);
    $second = $contract->fresh()->approvers->firstWhere('order', 2);

    expect($first->status)->toBe(ContractApprover::STATUS_PENDING)
        ->and($first->due_at)->not->toBeNull()
        ->and($second->status)->toBe(ContractApprover::STATUS_QUEUED)
        ->and($second->due_at)->toBeNull();
});

it('promotes the next QUEUED approver to PENDING when the current one approves', function () {
    [$contract, , $approvers] = chainForSubmit(3);
    $contract->update(['status' => Contract::STATUS_IN_REVIEW]);
    $contract->approvers()->where('order', 1)->first()->startReview(2);

    actingAs($approvers->first());
    app(ContractWorkflow::class)->approve($contract, $approvers->first());

    $second = $contract->fresh()->approvers->firstWhere('order', 2);

    expect($second->status)->toBe(ContractApprover::STATUS_PENDING)
        ->and($second->due_at)->not->toBeNull();
});
