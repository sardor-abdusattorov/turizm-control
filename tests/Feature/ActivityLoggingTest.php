<?php

use App\Models\Contract;
use App\Models\ContractApprover;
use App\Models\ContractTemplate;
use App\Models\User;
use App\Services\Contracts\ContractWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('local');

    config([
        'onlyoffice.public_url' => 'http://onlyoffice',
        'onlyoffice.internal_url' => 'http://onlyoffice',
        'onlyoffice.callback_host' => 'http://nginx',
        'onlyoffice.jwt_secret' => 'test-secret',
    ]);
});

function chainOf(int $count = 3): array
{
    $responsible = User::factory()->create();
    $approvers = User::factory()->count($count)->create();

    $contract = Contract::factory()->withDocument()->create([
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

it('writes a Document activity log entry on OnlyOffice contract save', function () {
    Http::fake(['*/saved-contract.docx' => Http::response('%binary')]);

    [$contract] = chainOf(1);
    Storage::disk('local')->put($contract->documentPath(), 'fake');

    post(
        route('contracts.save-callback', [
            'contract' => $contract,
            'shared_key' => $contract->document_key,
        ]),
        signOnlyOfficeCallback([
            'status' => 2,
            'url' => 'http://onlyoffice/cache/files/saved-contract.docx',
        ]),
    )->assertOk();

    $activity = Activity::where('log_name', 'Document')
        ->where('event', 'Contract Document Saved')
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->subject_id)->toBe($contract->id);
});

it('writes a Document activity log entry on OnlyOffice template save', function () {
    Http::fake(['*/saved-template.docx' => Http::response('%binary')]);

    $template = ContractTemplate::factory()->create();
    Storage::disk('local')->put($template->template_file, 'fake');

    post(
        route('contract-templates.save-callback', [
            'template' => $template,
            'shared_key' => $template->document_key,
        ]),
        signOnlyOfficeCallback([
            'status' => 2,
            'url' => 'http://onlyoffice/cache/files/saved-template.docx',
        ]),
    )->assertOk();

    $activity = Activity::where('log_name', 'Document')
        ->where('event', 'Template Saved')
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->subject_id)->toBe($template->id);
});

it('uses Template Forcesave event for status 6 callbacks', function () {
    Http::fake(['*/saved-template.docx' => Http::response('%binary')]);

    $template = ContractTemplate::factory()->create();
    Storage::disk('local')->put($template->template_file, 'fake');

    post(
        route('contract-templates.save-callback', [
            'template' => $template,
            'shared_key' => $template->document_key,
        ]),
        signOnlyOfficeCallback([
            'status' => 6,
            'url' => 'http://onlyoffice/cache/files/saved-template.docx',
        ]),
    )->assertOk();

    expect(Activity::where('event', 'Template Forcesave')->exists())->toBeTrue();
});
