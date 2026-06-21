<?php

use App\Models\Contract;
use App\Models\ContractApprover;
use App\Models\Department;
use App\Models\Settings;
use App\Models\User;
use App\Services\Contracts\ContractWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
});

function settingsFlowAvailable(): void
{
    foreach (['legal' => 'Lawyer X', 'accounting' => 'Accountant X', 'direction' => 'Director X'] as $code => $name) {
        $head = User::factory()->create(['name' => $name, 'status' => true]);
        $dept = Department::create([
            'code' => $code,
            'name' => ['ru' => $code, 'uz' => $code, 'en' => $code],
            'head_of_department' => $head->id,
            'sort' => 1,
            'status' => true,
        ]);
        $head->update(['department_id' => $dept->id]);
    }
    Settings::set('approval.flow', ['legal', 'accounting', 'direction']);
    clear_settings_cache();
}

function inReviewContractWithChain(): array
{
    $responsible = User::factory()->create();
    $approvers = User::factory()->count(3)->create();

    $contract = Contract::factory()->withDocument()->create([
        'responsible_id' => $responsible->id,
        'status' => Contract::STATUS_IN_REVIEW,
    ]);

    foreach ($approvers as $i => $u) {
        ContractApprover::factory()->create([
            'contract_id' => $contract->id,
            'user_id' => $u->id,
            'order' => $i + 1,
        ]);
    }

    // First approver already said yes
    $contract->approvers()->where('order', 1)->first()->markApproved();

    return [$contract->refresh(), $responsible, $approvers];
}

it('cancels every existing approver and drops the contract back to draft when a business field is edited', function () {
    [$contract] = inReviewContractWithChain();

    $contract->update(['title' => 'A new title']);

    expect($contract->fresh()->status)->toBe(Contract::STATUS_DRAFT)
        ->and($contract->approvers()->where('status', ContractApprover::STATUS_INVALIDATED)->count())->toBe(3);
});

it('keeps existing approvers when only bookkeeping fields change (status, document_key, signed_at)', function () {
    [$contract] = inReviewContractWithChain();

    $contract->update(['document_key' => 'fresh-key-12345']);

    expect($contract->fresh()->status)->toBe(Contract::STATUS_IN_REVIEW)
        ->and($contract->approvers()->where('status', ContractApprover::STATUS_INVALIDATED)->count())->toBe(0);
});

it('rebuilds the approver queue from the settings flow when submit runs against an all-invalidated chain', function () {
    settingsFlowAvailable();
    [$contract, $responsible] = inReviewContractWithChain();

    // Edit -> all invalidated, back to draft
    $contract->update(['title' => 'Re-titled']);

    actingAs($responsible);
    expect(app(ContractWorkflow::class)->submit($contract->fresh()))->toBeTrue();

    expect($contract->fresh()->status)->toBe(Contract::STATUS_IN_REVIEW)
        ->and($contract->activeApprovers()->count())->toBeGreaterThan(0)
        ->and($contract->approvers()->where('status', ContractApprover::STATUS_INVALIDATED)->count())->toBe(3);
});

it('preserveApprovedOnNextSave keeps an approved contract approved through an edit', function () {
    [$contract] = inReviewContractWithChain();
    $contract->update(['status' => Contract::STATUS_APPROVED]);
    $contract->approvers()->update(['status' => ContractApprover::STATUS_APPROVED]);

    $contract->preserveApprovedOnNextSave()->update(['title' => 'fix typo']);

    expect($contract->fresh()->status)->toBe(Contract::STATUS_APPROVED)
        ->and($contract->approvers()->where('status', ContractApprover::STATUS_INVALIDATED)->count())->toBe(0);
});

it('does not invalidate when the contract is still a draft', function () {
    $responsible = User::factory()->create();
    $contract = Contract::factory()->create([
        'responsible_id' => $responsible->id,
        'status' => Contract::STATUS_DRAFT,
    ]);
    $approver = User::factory()->create();
    ContractApprover::factory()->create([
        'contract_id' => $contract->id, 'user_id' => $approver->id, 'order' => 1,
    ]);

    $contract->update(['title' => 'still a draft']);

    expect($contract->fresh()->approvers()->where('status', ContractApprover::STATUS_INVALIDATED)->count())->toBe(0);
});
