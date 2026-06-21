<?php

use App\Models\Contract;
use App\Models\ContractApprover;
use App\Models\User;
use App\Services\Contracts\ContractWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
});

it('skips inactive approvers when the contract is submitted', function () {
    $responsible = User::factory()->create();
    $inactiveLawyer = User::factory()->create(['status' => false]);
    $activeAccountant = User::factory()->create(['status' => true]);

    $contract = Contract::factory()->withDocument()->create([
        'responsible_id' => $responsible->id,
        'status' => Contract::STATUS_DRAFT,
    ]);

    ContractApprover::factory()->create([
        'contract_id' => $contract->id, 'user_id' => $inactiveLawyer->id, 'order' => 1,
    ]);
    ContractApprover::factory()->create([
        'contract_id' => $contract->id, 'user_id' => $activeAccountant->id, 'order' => 2,
    ]);

    actingAs($responsible);
    app(ContractWorkflow::class)->submit($contract);

    $first = $contract->approvers()->where('order', 1)->first();
    $second = $contract->approvers()->where('order', 2)->first();

    expect($first->status)->toBe(ContractApprover::STATUS_SKIPPED)
        ->and($second->status)->toBe(ContractApprover::STATUS_PENDING)
        ->and($second->due_at)->not->toBeNull();
});

it('jumps past an inactive approver when the previous one approves', function () {
    $responsible = User::factory()->create();
    $lawyer = User::factory()->create(['status' => true]);
    $inactiveAccountant = User::factory()->create(['status' => false]);
    $director = User::factory()->create(['status' => true]);

    $contract = Contract::factory()->create([
        'responsible_id' => $responsible->id,
        'status' => Contract::STATUS_IN_REVIEW,
    ]);

    foreach ([$lawyer, $inactiveAccountant, $director] as $i => $u) {
        ContractApprover::factory()->create([
            'contract_id' => $contract->id, 'user_id' => $u->id, 'order' => $i + 1,
        ]);
    }

    actingAs($lawyer);
    app(ContractWorkflow::class)->approve($contract, $lawyer);

    $accountant = $contract->approvers()->where('order', 2)->first();
    $directorRow = $contract->approvers()->where('order', 3)->first();

    expect($accountant->status)->toBe(ContractApprover::STATUS_SKIPPED)
        ->and($directorRow->status)->toBe(ContractApprover::STATUS_PENDING);
});
