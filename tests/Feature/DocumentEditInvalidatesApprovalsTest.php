<?php

use App\Models\Contract;
use App\Models\ContractApprover;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\post;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');

    config([
        'onlyoffice.public_url' => 'http://onlyoffice',
        'onlyoffice.internal_url' => 'http://onlyoffice',
        'onlyoffice.callback_host' => 'http://nginx',
        'onlyoffice.jwt_secret' => 'test-secret',
    ]);
});

/**
 * Build an in-review contract whose chain is [APPROVED, PENDING, QUEUED] —
 * the partial-progress shape the OnlyOffice-save regression cases below
 * operate on.
 *
 * @return array{Contract, list<User>}
 */
function docInReviewContractWithPartialProgress(): array
{
    $responsible = User::factory()->create();
    $approvers = User::factory()->count(3)->create(['status' => User::STATUS_ACTIVE]);

    $contract = Contract::factory()->create([
        'responsible_id' => $responsible->id,
        'status' => Contract::STATUS_IN_REVIEW,
    ]);

    foreach ($approvers as $i => $user) {
        ContractApprover::factory()->create([
            'contract_id' => $contract->id,
            'user_id' => $user->id,
            'order' => $i + 1,
            'status' => match ($i) {
                0 => ContractApprover::STATUS_APPROVED,
                1 => ContractApprover::STATUS_PENDING,
                default => ContractApprover::STATUS_QUEUED,
            },
        ]);
    }

    return [$contract->refresh(), $approvers->all()];
}

it('cancels approvals when OnlyOffice saves the document on an in-review contract', function () {
    Http::fake(['*/edited.docx' => Http::response('updated-docx-body')]);

    [$contract, $approvers] = docInReviewContractWithPartialProgress();
    Storage::disk('local')->put($contract->documentPath(), 'original');

    post(
        route('contracts.save-callback', [
            'contract' => $contract,
            'shared_key' => $contract->document_key,
        ]),
        [
            'status' => 2,
            'url' => 'http://onlyoffice/cache/files/edited.docx',
        ],
    )->assertOk();

    $contract->refresh();

    expect($contract->status)->toBe(Contract::STATUS_DRAFT)
        ->and($contract->signed_at)->toBeNull()
        ->and($contract->approvers()->where('status', ContractApprover::STATUS_INVALIDATED)->count())->toBe(3)
        ->and($contract->approvers()->where('status', ContractApprover::STATUS_QUEUED)->orderBy('order')->pluck('user_id')->all())
        ->toBe(collect($approvers)->pluck('id')->values()->all())
        ->and(Storage::disk('local')->get($contract->documentPath()))->toBe('updated-docx-body');
});

it('leaves a draft contract untouched when OnlyOffice saves the document', function () {
    Http::fake(['*/edited.docx' => Http::response('updated-docx-body')]);

    $contract = Contract::factory()->create(['status' => Contract::STATUS_DRAFT]);
    Storage::disk('local')->put($contract->documentPath(), 'original');

    post(
        route('contracts.save-callback', [
            'contract' => $contract,
            'shared_key' => $contract->document_key,
        ]),
        [
            'status' => 2,
            'url' => 'http://onlyoffice/cache/files/edited.docx',
        ],
    )->assertOk();

    expect($contract->fresh()->status)->toBe(Contract::STATUS_DRAFT)
        ->and(Storage::disk('local')->get($contract->documentPath()))->toBe('updated-docx-body');
});

it('does not invalidate approvals on an OnlyOffice forcesave status that did not finalise', function () {
    Http::fake(['*/edited.docx' => Http::response('forcesave-body')]);

    [$contract] = docInReviewContractWithPartialProgress();
    Storage::disk('local')->put($contract->documentPath(), 'original');

    // status 6 = forcesave: file gets written but the editor session is
    // still live, so we keep the contract in review.
    post(
        route('contracts.save-callback', [
            'contract' => $contract,
            'shared_key' => $contract->document_key,
        ]),
        [
            'status' => 6,
            'url' => 'http://onlyoffice/cache/files/edited.docx',
        ],
    )->assertOk();

    $contract->refresh();

    expect($contract->status)->toBe(Contract::STATUS_IN_REVIEW)
        ->and($contract->approvers()->where('status', ContractApprover::STATUS_INVALIDATED)->count())->toBe(0);
});
