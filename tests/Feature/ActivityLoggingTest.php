<?php

use App\Models\Contract;
use App\Models\ContractApprover;
use App\Models\User;
use App\Services\Contracts\ContractWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
});

function chainOf(int $count = 3): array
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
        ]);
    }

    return [$contract->refresh(), $responsible, $approvers];
}

it('writes a workflow activity log entry when a contract is submitted', function () {
    [$contract, $responsible, $approvers] = chainOf();
    actingAs($responsible);

    app(ContractWorkflow::class)->submit($contract);

    $activity = Activity::where('log_name', 'Workflow')
        ->where('event', 'Contract Submitted')
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->subject_id)->toBe($contract->id)
        ->and($activity->causer_id)->toBe($responsible->id)
        ->and($activity->properties->get('next_approver_id'))->toBe($approvers->first()->id);
});

it('writes step-approved and final approved entries through the chain', function () {
    [$contract, , $approvers] = chainOf(2);
    $contract->update(['status' => Contract::STATUS_IN_REVIEW]);
    actingAs($approvers->first());

    app(ContractWorkflow::class)->approve($contract, $approvers->first(), 'looks good');

    expect(Activity::where('event', 'Contract Step Approved')->exists())->toBeTrue();

    actingAs($approvers->last());
    app(ContractWorkflow::class)->approve($contract->fresh(), $approvers->last());

    $final = Activity::where('event', 'Contract Approved')->latest('id')->first();
    expect($final)->not->toBeNull()
        ->and($final->properties->get('final'))->toBeTrue();
});

it('writes a reject activity entry', function () {
    [$contract, , $approvers] = chainOf(2);
    $contract->update(['status' => Contract::STATUS_IN_REVIEW]);
    actingAs($approvers->first());

    app(ContractWorkflow::class)->reject($contract, $approvers->first(), 'amount mismatch');
    expect(Activity::where('event', 'Contract Rejected')->exists())->toBeTrue();
});
