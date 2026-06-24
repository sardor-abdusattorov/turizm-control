<?php

use App\Enums\ContractStatus;
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
        signOnlyOfficeCallback([
            'status' => 2,
            'url' => 'http://onlyoffice/cache/files/edited.docx',
        ]),
    )->assertOk();

    $contract->refresh();

    expect($contract->status)->toBe(Contract::STATUS_DRAFT)
        ->and($contract->signed_at)->toBeNull()
        ->and($contract->approvers()->where('status', ContractApprover::STATUS_INVALIDATED)->count())->toBe(3)
        ->and($contract->approvers()->where('status', ContractApprover::STATUS_QUEUED)->orderBy('order')->pluck('user_id')->all())
        ->toBe(collect($approvers)->pluck('id')->values()->all())
        ->and(Storage::disk('local')->get($contract->documentPath()))->toBe('updated-docx-body');
});

it('resets the chain when the responsible manager (a non-approver) edits the document', function () {
    Http::fake(['*/edited.docx' => Http::response('manager-edited-body')]);

    [$contract, $approvers] = docInReviewContractWithPartialProgress();
    Storage::disk('local')->put($contract->documentPath(), 'original');

    // The manager who owns the contract is the responsible user — never part
    // of the approver chain. Their save MUST bounce the contract to Draft.
    $manager = User::find($contract->responsible_id);

    post(
        route('contracts.save-callback', [
            'contract' => $contract,
            'shared_key' => $contract->document_key,
        ]),
        signOnlyOfficeCallback([
            'status' => 2,
            'url' => 'http://onlyoffice/cache/files/edited.docx',
            'users' => [(string) $manager->id],
        ]),
    )->assertOk();

    $contract->refresh();

    expect($contract->status)->toBe(Contract::STATUS_DRAFT)
        ->and($contract->approvers()->where('status', ContractApprover::STATUS_INVALIDATED)->count())->toBe(3)
        ->and($contract->approvers()->where('status', ContractApprover::STATUS_QUEUED)->count())->toBe(3);
});

it('does NOT reset when the current approver tweaks the document', function () {
    Http::fake(['*/edited.docx' => Http::response('updated-docx-body')]);

    [$contract, $approvers] = docInReviewContractWithPartialProgress();
    Storage::disk('local')->put($contract->documentPath(), 'original');

    // approvers[1] is the PENDING one (the current approver). OnlyOffice
    // fires a save just from them opening + closing the editor — that must
    // not bounce the contract back to Draft and hide the approve buttons.
    $currentApprover = $approvers[1];

    post(
        route('contracts.save-callback', [
            'contract' => $contract,
            'shared_key' => $contract->document_key,
        ]),
        signOnlyOfficeCallback([
            'status' => 2,
            'url' => 'http://onlyoffice/cache/files/edited.docx',
            'users' => [(string) $currentApprover->id],
        ]),
    )->assertOk();

    $contract->refresh();

    expect($contract->status)->toBe(Contract::STATUS_IN_REVIEW)
        ->and($contract->approvers()->where('status', ContractApprover::STATUS_INVALIDATED)->count())->toBe(0)
        // the document is still written to disk — only the chain is left alone.
        ->and(Storage::disk('local')->get($contract->documentPath()))->toBe('updated-docx-body');
});

it('resets when the current approver co-edits alongside someone else', function () {
    Http::fake(['*/edited.docx' => Http::response('co-edited-body')]);

    [$contract, $approvers] = docInReviewContractWithPartialProgress();
    Storage::disk('local')->put($contract->documentPath(), 'original');

    // approvers[1] is the PENDING approver; the responsible manager edited the
    // document in the same session. Because someone other than the current
    // approver touched it, the chain is stale and must reset.
    $currentApprover = $approvers[1];
    $manager = User::find($contract->responsible_id);

    post(
        route('contracts.save-callback', [
            'contract' => $contract,
            'shared_key' => $contract->document_key,
        ]),
        signOnlyOfficeCallback([
            'status' => 2,
            'url' => 'http://onlyoffice/cache/files/edited.docx',
            'users' => [(string) $currentApprover->id, (string) $manager->id],
        ]),
    )->assertOk();

    $contract->refresh();

    expect($contract->status)->toBe(Contract::STATUS_DRAFT)
        ->and($contract->approvers()->where('status', ContractApprover::STATUS_INVALIDATED)->count())->toBe(3);
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
        signOnlyOfficeCallback([
            'status' => 2,
            'url' => 'http://onlyoffice/cache/files/edited.docx',
        ]),
    )->assertOk();

    expect($contract->fresh()->status)->toBe(Contract::STATUS_DRAFT)
        ->and(Storage::disk('local')->get($contract->documentPath()))->toBe('updated-docx-body');
});

it('flags that editing would reset approvals only while under approval', function () {
    $flag = fn (ContractStatus $status): bool => (new Contract(['status' => $status]))->documentEditWouldResetApprovals();

    expect($flag(ContractStatus::InReview))->toBeTrue()
        ->and($flag(ContractStatus::PendingDirector))->toBeTrue()
        ->and($flag(ContractStatus::InReviewDirector))->toBeTrue()
        ->and($flag(ContractStatus::Draft))->toBeFalse()
        ->and($flag(ContractStatus::Approved))->toBeFalse()
        ->and($flag(ContractStatus::Rejected))->toBeFalse();
});

it('does not invalidate approvals on an anonymous forcesave with no named editor', function () {
    Http::fake(['*/edited.docx' => Http::response('forcesave-body')]);

    [$contract] = docInReviewContractWithPartialProgress();
    Storage::disk('local')->put($contract->documentPath(), 'original');

    // status 6 forcesave with no `users` — a timed/system autosave we can't
    // attribute to anyone. Since we can't tell an author's change from the
    // current approver's tweak, we leave the reset to the status-2 save.
    post(
        route('contracts.save-callback', [
            'contract' => $contract,
            'shared_key' => $contract->document_key,
        ]),
        signOnlyOfficeCallback([
            'status' => 6,
            'url' => 'http://onlyoffice/cache/files/edited.docx',
        ]),
    )->assertOk();

    $contract->refresh();

    expect($contract->status)->toBe(Contract::STATUS_IN_REVIEW)
        ->and($contract->approvers()->where('status', ContractApprover::STATUS_INVALIDATED)->count())->toBe(0);
});

it('resets approvals on a forcesave when a non-approver actually edits the document', function () {
    // The real-world bug: the author edits, OnlyOffice flushes the change via a
    // forcesave (status 6), and the closing save reports no further changes — so
    // the edit used to slip through without resetting the chain.
    Http::fake(['*/edited.docx' => Http::response('manager-forcesave-body')]);

    [$contract] = docInReviewContractWithPartialProgress();
    Storage::disk('local')->put($contract->documentPath(), 'original');

    $manager = User::find($contract->responsible_id);

    post(
        route('contracts.save-callback', [
            'contract' => $contract,
            'shared_key' => $contract->document_key,
        ]),
        signOnlyOfficeCallback([
            'status' => 6,
            'url' => 'http://onlyoffice/cache/files/edited.docx',
            'users' => [(string) $manager->id],
        ]),
    )->assertOk();

    $contract->refresh();

    expect($contract->status)->toBe(Contract::STATUS_DRAFT)
        ->and($contract->approvers()->where('status', ContractApprover::STATUS_INVALIDATED)->count())->toBe(3)
        ->and(Storage::disk('local')->get($contract->documentPath()))->toBe('manager-forcesave-body');
});

it('does not reset when the saved document is identical to the stored one', function () {
    // The other half of the bug: just opening and closing the editor emits a
    // save whose bytes match what is already on disk. Nothing really changed,
    // so the chain must stay live.
    Http::fake(['*/edited.docx' => Http::response('identical-bytes')]);

    [$contract] = docInReviewContractWithPartialProgress();
    Storage::disk('local')->put($contract->documentPath(), 'identical-bytes');

    $manager = User::find($contract->responsible_id);

    post(
        route('contracts.save-callback', [
            'contract' => $contract,
            'shared_key' => $contract->document_key,
        ]),
        signOnlyOfficeCallback([
            'status' => 2,
            'url' => 'http://onlyoffice/cache/files/edited.docx',
            'users' => [(string) $manager->id],
        ]),
    )->assertOk();

    $contract->refresh();

    expect($contract->status)->toBe(Contract::STATUS_IN_REVIEW)
        ->and($contract->approvers()->where('status', ContractApprover::STATUS_INVALIDATED)->count())->toBe(0);
});
