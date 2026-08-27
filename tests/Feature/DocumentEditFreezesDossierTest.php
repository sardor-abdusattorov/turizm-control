<?php

use App\Enums\ContractStatus;
use App\Models\Contract;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The rest of this file used to cover the OnlyOffice save-callback, which is
 * gone. What survives is the rule it shared with the dossier: while a
 * contract is under approval its files freeze, so approvers review a fixed
 * set rather than a moving target (Contract::attachmentsManageableBy).
 */
it('flags that editing would reset approvals only while under approval', function () {
    $flag = fn (ContractStatus $status): bool => (new Contract(['status' => $status]))->documentEditWouldResetApprovals();

    expect($flag(ContractStatus::InReview))->toBeTrue()
        ->and($flag(ContractStatus::PendingDirector))->toBeTrue()
        ->and($flag(ContractStatus::InReviewDirector))->toBeTrue()
        ->and($flag(ContractStatus::Draft))->toBeFalse()
        ->and($flag(ContractStatus::Approved))->toBeFalse()
        ->and($flag(ContractStatus::Rejected))->toBeFalse();
});
