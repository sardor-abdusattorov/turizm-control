<?php

use App\Enums\ContractStatus;
use App\Models\Contract;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('flags that editing would reset approvals only while under approval', function () {
    $flag = fn (ContractStatus $status): bool => (new Contract(['status' => $status]))->documentEditWouldResetApprovals();

    expect($flag(ContractStatus::InReview))->toBeTrue()
        ->and($flag(ContractStatus::PendingDirector))->toBeTrue()
        ->and($flag(ContractStatus::InReviewDirector))->toBeTrue()
        ->and($flag(ContractStatus::Draft))->toBeFalse()
        ->and($flag(ContractStatus::Approved))->toBeFalse()
        ->and($flag(ContractStatus::Rejected))->toBeFalse();
});
